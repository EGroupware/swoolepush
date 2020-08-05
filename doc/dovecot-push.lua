-- To use
--
-- plugin {
--  push_notification_driver = lua:file=/etc/dovecot/dovecot-push.lua
--  push_lua_url = https://Bearer:<push-token>@<egroupware-domain>/egroupware/push
-- }
--
-- server is sent a PUT message with JSON body like push_notification_driver = ox:url=<push_lua_url> user_from_metadata
-- plus additionally the events MessageAppend, MessageExpunge, FlagsSet and FlagsClear
-- MessageTrash and MessageRead are ignored, so are empty FlagSet/Clear or FlagSet NonJunk (TB)
--

local http = require "socket.http"
local ltn12 = require "ltn12"
-- luarocks install json-lua
local json = require "JSON"

function table_get(t, k, d)
  return t[k] or d
end

function script_init()
  return 0
end

function dovecot_lua_notify_begin_txn(user)
    local meta = user:metadata_get("/private/vendor/vendor.dovecot/http-notify")
    if (meta == nil or meta:sub(1,5) ~= "user=")
    then
        meta = nil;
    else
        meta = meta:sub(6)
    end
    return {user=user, event=dovecot.event(), ep=user:plugin_getenv("push_lua_url"), messages={}, meta=meta}
end

function dovecot_lua_notify_event_message_new(ctx, event)
    -- check if there is a push token registered
    if (ctx.meta == nil) then
        return
    end
    -- get mailbox status
    local mbox = ctx.user:mailbox(event.mailbox)
    mbox:sync()
    local status = mbox:status(dovecot.storage.STATUS_RECENT, dovecot.storage.STATUS_UNSEEN, dovecot.storage.STATUS_MESSAGES)
    mbox:free()
    table.insert(ctx.messages, {
        user = ctx.meta,
        ["imap-uidvalidity"] = event.uid_validity,
        ["imap-uid"] = event.uid,
        folder = event.mailbox,
        event = event.name,
        from = event.from,
        subject = event.subject,
        snippet = event.snippet,
        unseen = status.unseen,
        messages = status.messages
    })
end

function dovecot_lua_notify_event_message_append(ctx, event)
  dovecot_lua_notify_event_message_new(ctx, event)
end

-- ignored, as FlagSet flags=[\Seen] is sent anyway too
-- function dovecot_lua_notify_event_message_read(ctx, event)
--    dovecot_lua_notify_event_message_expunge(ctx, event)
-- end

-- ignored, as most MUA nowadays expunge immediatly
-- function dovecot_lua_notify_event_message_trash(ctx, event)
--    dovecot_lua_notify_event_message_expunge(ctx, event)
-- end

function dovecot_lua_notify_event_message_expunge(ctx, event)
    -- check if there is a push token registered
    if (ctx.meta == nil) then
        return
    end
    -- get mailbox status
    local mbox = ctx.user:mailbox(event.mailbox)
    mbox:sync()
    local status = mbox:status(dovecot.storage.STATUS_RECENT, dovecot.storage.STATUS_UNSEEN, dovecot.storage.STATUS_MESSAGES)
    mbox:free()
    -- agregate multiple Expunge (or Trash or Read)
    if (#ctx.messages == 1 and ctx.messages[1].user == ctx.meta and ctx.messages[1].folder == event.mailbox and
        ctx.messages[1]["imap-uidvalidity"] == event.uid_validity and ctx.messages[1].event == event.name)
    then
        if (type(ctx.messages[1]["imap-uid"]) ~= 'table') then
            ctx.messages[1]["imap-uid"] = {ctx.messages[1]["imap-uid"]}
        end
        table.insert(ctx.messages[1]["imap-uid"], event.uid)
        ctx.messages[1].unseen = status.unseen
        ctx.messages[1].messages = status.messages
        return;
    end
    table.insert(ctx.messages, {
        user = ctx.meta,
        ["imap-uidvalidity"] = event.uid_validity,
        ["imap-uid"] = event.uid,
        folder = event.mailbox,
        event = event.name,
        unseen = status.unseen,
        messages = status.messages
    })
end

function dovecot_lua_notify_event_flags_set(ctx, event)
    -- check if there is a push token registered
    if (ctx.meta == nil or
        (#event.flags == 0 and #event.keywords == 0) or -- ignore TB sends it empty
        (#event.keywords == 1 and event.keywords[1] == "NonJunk")) -- ignore TB NonJunk
    then
        return
    end
    local status = nil;
    if (#event.flags == 1 and event.flags[1] == "\\Seen")
    then
        -- get mailbox status
        local mbox = ctx.user:mailbox(event.mailbox)
        mbox:sync()
        status = mbox:status(dovecot.storage.STATUS_RECENT, dovecot.storage.STATUS_UNSEEN, dovecot.storage.STATUS_MESSAGES)
        mbox:free()
    end
    -- agregate multiple FlagSet
    if (#ctx.messages == 1 and ctx.messages[1].user == ctx.meta and ctx.messages[1].folder == event.mailbox and
        ctx.messages[1]["imap-uidvalidity"] == event.uid_validity and ctx.messages[1].event == event.name and
        arrayEqual(ctx.messages[1].flags, event.flags) and arrayEqual(ctx.messages[1].keywords, event.keywords))
    then
        if (type(ctx.messages[1]["imap-uid"]) ~= 'table') then
            ctx.messages[1]["imap-uid"] = {ctx.messages[1]["imap-uid"]}
        end
        table.insert(ctx.messages[1]["imap-uid"], event.uid)
        if (status ~= nil)
        then
            ctx.messages[1].unseen = status.unseen
        end
        return;
    end
    local msg = {
        user = ctx.meta,
        ["imap-uidvalidity"] = event.uid_validity,
        ["imap-uid"] = event.uid,
        folder = event.mailbox,
        event = event.name,
        flags = event.flags,
        keywords = event.keywords
    }
    if (status ~= nil)
    then
        msg.unseen = status.unseen
    end
    if (event.name == "FlagsClear")
    then
        msg["flags-old"] = event.flags_old
        msg["keywords-old"] = event.keywords_old
    end
    table.insert(ctx.messages, msg)
end

function arrayEqual(t1, t2)
    if (#t1 ~= #t2)
    then
        return false
    end
    if (#t1 == 1 and t1[1] == t2[1])
    then
        return true
    end
    return json:encode(t1) == json:encode(t2)
end

function dovecot_lua_notify_event_flags_clear(ctx, event)
    dovecot_lua_notify_event_flags_set(ctx, event)
end

function dovecot_lua_notify_end_txn(ctx)
    -- report all states
    for i,msg in ipairs(ctx.messages) do
        local e = dovecot.event(ctx.event)
        e:set_name("lua_notify_mail_finished")
        reqbody = json:encode(msg)
        e:log_debug(ctx.ep .. " - sending " .. reqbody)
        res, code = http.request({
            method = "PUT",
            url = ctx.ep,
            source = ltn12.source.string(reqbody),
            headers={
                ["content-type"] = "application/json; charset=utf-8",
                ["content-length"] = tostring(#reqbody)
            }
        })
        e:add_int("result_code", code)
        e:log_info("Mail notify status " .. tostring(code))
    end
end
