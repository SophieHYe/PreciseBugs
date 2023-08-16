//////////////////////////////////////////////////////////////////////////////////////
//    akashi - a server for Attorney Online 2                                       //
//    Copyright (C) 2020  scatterflower                                           //
//                                                                                  //
//    This program is free software: you can redistribute it and/or modify          //
//    it under the terms of the GNU Affero General Public License as                //
//    published by the Free Software Foundation, either version 3 of the            //
//    License, or (at your option) any later version.                               //
//                                                                                  //
//    This program is distributed in the hope that it will be useful,               //
//    but WITHOUT ANY WARRANTY{} without even the implied warranty of                //
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                 //
//    GNU Affero General Public License for more details.                           //
//                                                                                  //
//    You should have received a copy of the GNU Affero General Public License      //
//    along with this program.  If not, see <https://www.gnu.org/licenses/>.        //
//////////////////////////////////////////////////////////////////////////////////////
#include "include/aoclient.h"

void AOClient::pktDefault(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
#ifdef NET_DEBUG
    qDebug() << "Unimplemented packet:" << packet.header << packet.contents;
#endif
}

void AOClient::pktHardwareId(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    hwid = argv[0];
    auto ban = server->db_manager->isHDIDBanned(hwid);
    if (ban.first) {
        sendPacket("BD", {ban.second + "\nBan ID: " + QString::number(server->db_manager->getBanID(hwid))});
        socket->close();
        return;
    }
    sendPacket("ID", {QString::number(id), "akashi", QCoreApplication::applicationVersion()});
}

void AOClient::pktSoftwareId(AreaData* area, int argc, QStringList argv, AOPacket packet)
{


    // Full feature list as of AO 2.8.5
    // The only ones that are critical to ensuring the server works are
    // "noencryption" and "fastloading"
    QStringList feature_list = {
        "noencryption", "yellowtext",         "prezoom",
        "flipping",     "customobjections",   "fastloading",
        "deskmod",      "evidence",           "cccc_ic_support",
        "arup",         "casing_alerts",      "modcall_reason",
        "looping_sfx",  "additive",           "effects",
        "y_offset",     "expanded_desk_mods", "auth_packet"
    };


    version.string = argv[1];
    QRegularExpression rx("\\b(\\d+)\\.(\\d+)\\.(\\d+)\\b"); // matches X.X.X (e.g. 2.9.0, 2.4.10, etc.)
    QRegularExpressionMatch match = rx.match(version.string);
    if (match.hasMatch()) {
        version.release = match.captured(1).toInt();
        version.major = match.captured(2).toInt();
        version.minor = match.captured(3).toInt();
    }

    sendPacket("PN", {QString::number(server->player_count), QString::number(ConfigManager::maxPlayers())});
    sendPacket("FL", feature_list);

    if (ConfigManager::assetUrl().isValid()) {
    QByteArray asset_url = ConfigManager::assetUrl().toEncoded(QUrl::EncodeSpaces);
    sendPacket("ASS", {asset_url});
    }
}

void AOClient::pktBeginLoad(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    // Evidence isn't loaded during this part anymore
    // As a result, we can always send "0" for evidence length
    // Client only cares about what it gets from LE
    sendPacket("SI", {QString::number(server->characters.length()), "0", QString::number(server->area_names.length() + server->music_list.length())});
}

void AOClient::pktRequestChars(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    sendPacket("SC", server->characters);
}

void AOClient::pktRequestMusic(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    sendPacket("SM", server->area_names + server->music_list);
}

void AOClient::pktLoadingDone(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    if (hwid == "") {
        // No early connecting!
        socket->close();
        return;
    }

    if (joined) {
        return;
    }

    server->player_count++;
    area->clientJoinedArea();
    joined = true;
    server->updateCharsTaken(area);

    arup(ARUPType::PLAYER_COUNT, true); // Tell everyone there is a new player
    sendEvidenceList(area);

    sendPacket("HP", {"1", QString::number(area->defHP())});
    sendPacket("HP", {"2", QString::number(area->proHP())});
    sendPacket("FA", server->area_names);
    //Here lies OPPASS, the genius of FanatSors who send the modpass to everyone in plain text.
    sendPacket("DONE");
    sendPacket("BN", {area->background()});
  
    sendServerMessage("=== MOTD ===\r\n" + ConfigManager::motd() + "\r\n=============");

    fullArup(); // Give client all the area data
    if (server->timer->isActive()) {
        sendPacket("TI", {"0", "2"});
        sendPacket("TI", {"0", "0", QString::number(QTime(0,0).msecsTo(QTime(0,0).addMSecs(server->timer->remainingTime())))});
    }
    else {
        sendPacket("TI", {"0", "3"});
    }
    for (QTimer* timer : area->timers()) {
        int timer_id = area->timers().indexOf(timer) + 1;
        if (timer->isActive()) {
            sendPacket("TI", {QString::number(timer_id), "2"});
            sendPacket("TI", {QString::number(timer_id), "0", QString::number(QTime(0,0).msecsTo(QTime(0,0).addMSecs(timer->remainingTime())))});
        }
        else {
            sendPacket("TI", {QString::number(timer_id), "3"});
        }
    }
}

void AOClient::pktCharPassword(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    password = argv[0];
}

void AOClient::pktSelectChar(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    bool argument_ok;
    int selected_char_id = argv[1].toInt(&argument_ok);
    if (!argument_ok) {
        selected_char_id = -1;
        return;
    }

    if (changeCharacter(selected_char_id))
        char_id = selected_char_id;
}

void AOClient::pktIcChat(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    if (is_muted) {
        sendServerMessage("You cannot speak while muted.");
        return;
    }

    if (!server->can_send_ic_messages) {
        return;
    }

    AOPacket validated_packet = validateIcPacket(packet);
    if (validated_packet.header == "INVALID")
        return;

    if (pos != "")
        validated_packet.contents[5] = pos;

    area->log(current_char, ipid, validated_packet);
    server->broadcast(validated_packet, current_area);
    area->updateLastICMessage(validated_packet.contents);

    server->can_send_ic_messages = false;
    server->next_message_timer.start(ConfigManager::messageFloodguard());
}

void AOClient::pktOocChat(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    if (is_ooc_muted) {
        sendServerMessage("You are OOC muted, and cannot speak.");
        return;
    }

    ooc_name = dezalgo(argv[0]).replace(QRegExp("\\[|\\]|\\{|\\}|\\#|\\$|\\%|\\&"), ""); // no fucky wucky shit here
    if (ooc_name.isEmpty() || ooc_name == ConfigManager::serverName()) // impersonation & empty name protection
        return;

    if (ooc_name.length() > 30) {
        sendServerMessage("Your name is too long! Please limit it to under 30 characters.");
        return;
    }

    if (is_logging_in) {
        loginAttempt(argv[1]);
        return;
    }
    
    QString message = dezalgo(argv[1]);
    if (message.length() == 0 || message.length() > ConfigManager::maxCharacters())
        return;
    AOPacket final_packet("CT", {ooc_name, message, "0"});
    if(message.at(0) == '/') {
        QStringList cmd_argv = message.split(" ", QString::SplitBehavior::SkipEmptyParts);
        QString command = cmd_argv[0].trimmed().toLower();
        command = command.right(command.length() - 1);
        cmd_argv.removeFirst();
        int cmd_argc = cmd_argv.length();

        handleCommand(command, cmd_argc, cmd_argv);
        area->logCmd(current_char, ipid, command, cmd_argv);
        return;
    }
    else {
        server->broadcast(final_packet, current_area);
    }
    area->log(current_char, ipid, final_packet);
}

void AOClient::pktPing(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    // Why does this packet exist
    // At least Crystal made it useful
    // It is now used for ping measurement
    sendPacket("CHECK");
}

void AOClient::pktChangeMusic(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    // Due to historical reasons, this
    // packet has two functions:
    // Change area, and set music.

    // First, we check if the provided
    // argument is a valid song
    QString argument = argv[0];

    for (QString song : server->music_list) {
        if (song == argument || song == "~stop.mp3") { // ~stop.mp3 is a dummy track used by 2.9+
            // We have a song here
            if (is_dj_blocked) {
                sendServerMessage("You are blocked from changing the music.");
                return;
            }
            if (!area->isMusicAllowed() && !checkAuth(ACLFlags.value("CM"))) {
                sendServerMessage("Music is disabled in this area.");
                return;
            }
            QString effects;
            if (argc >= 4)
                effects = argv[3];
            else
                effects = "0";
            QString final_song;
            if (!argument.contains("."))
                final_song = "~stop.mp3";
            else
                final_song = argument;
            AOPacket music_change("MC", {final_song, argv[1], showname, "1", "0", effects});
            area->currentMusic() = final_song;
            area->musicPlayerBy() = showname;
            server->broadcast(music_change, current_area);
            return;
        }
    }

    for (int i = 0; i < server->area_names.length(); i++) {
        QString area = server->area_names[i];
        if(area == argument) {
            changeArea(i);
            break;
        }
    }
}

void AOClient::pktWtCe(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    if (is_wtce_blocked) {
        sendServerMessage("You are blocked from using the judge controls.");
        return;
    }
    if (QDateTime::currentDateTime().toSecsSinceEpoch() - last_wtce_time <= 5)
        return;
    last_wtce_time = QDateTime::currentDateTime().toSecsSinceEpoch();
    server->broadcast(packet, current_area);
    updateJudgeLog(area, this, "WT/CE");
}

void AOClient::pktHpBar(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    if (is_wtce_blocked) {
        sendServerMessage("You are blocked from using the judge controls.");
        return;
    }
    int l_newValue = argv.at(1).toInt();

    if (argv[0] == "1") {
        area->changeHP(AreaData::Side::DEFENCE, l_newValue);
    }
    else if (argv[0] == "2") {
        area->changeHP(AreaData::Side::PROSECUTOR, l_newValue);
    }

    server->broadcast(AOPacket("HP", {"1", QString::number(area->defHP())}), area->index());
    server->broadcast(AOPacket("HP", {"2", QString::number(area->proHP())}), area->index());

    updateJudgeLog(area, this, "updated the penalties");
}

void AOClient::pktWebSocketIp(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    // Special packet to set remote IP from the webao proxy
    // Only valid if from a local ip
    if (remote_ip.isLoopback()) {
#ifdef NET_DEBUG
        qDebug() << "ws ip set to" << argv[0];
#endif
        remote_ip = QHostAddress(argv[0]);
        calculateIpid();
        auto ban = server->db_manager->isIPBanned(ipid);
        if (ban.first) {
            sendPacket("BD", {ban.second});
            socket->close();
            return;
        }

        int multiclient_count = 0;
        for (AOClient* joined_client : server->clients) {
            if (remote_ip.isEqual(joined_client->remote_ip))
                multiclient_count++;
        }

        if (multiclient_count > ConfigManager::multiClientLimit()) {
            socket->close();
            return;
        }
    }
}

void AOClient::pktModCall(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    for (AOClient* client : server->clients) {
        if (client->authenticated)
            client->sendPacket(packet);
    }
    area->log(current_char, ipid, packet);

    if (ConfigManager::discordWebhookEnabled()) {
        QString name = ooc_name;
        if (ooc_name.isEmpty())
            name = current_char;

        emit server->modcallWebhookRequest(name, server->areas[current_area]->name(), packet.contents[0], area->buffer());
    }
    
    area->flushLogs();
}

void AOClient::pktAddEvidence(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    if (!checkEvidenceAccess(area))
        return;
    AreaData::Evidence evi = {argv[0], argv[1], argv[2]};
    area->appendEvidence(evi);
    sendEvidenceList(area);
}

void AOClient::pktRemoveEvidence(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    if (!checkEvidenceAccess(area))
        return;
    bool is_int = false;
    int idx = argv[0].toInt(&is_int);
    if (is_int && idx < area->evidence().size() && idx >= 0) {
        area->deleteEvidence(idx);
    }
    sendEvidenceList(area);
}

void AOClient::pktEditEvidence(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    if (!checkEvidenceAccess(area))
        return;
    bool is_int = false;
    int idx = argv[0].toInt(&is_int);
    AreaData::Evidence evi = {argv[1], argv[2], argv[3]};
    if (is_int && idx < area->evidence().size() && idx >= 0) {
        area->replaceEvidence(idx, evi);
    }
    sendEvidenceList(area);
}

void AOClient::pktSetCase(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    QList<bool> prefs_list;
    for (int i = 2; i <=6; i++) {
        bool is_int = false;
        bool pref = argv[i].toInt(&is_int);
        if (!is_int)
            return;
        prefs_list.append(pref);
    }
    casing_preferences = prefs_list;
}

void AOClient::pktAnnounceCase(AreaData* area, int argc, QStringList argv, AOPacket packet)
{
    QString case_title = argv[0];
    QStringList needed_roles;
    QList<bool> needs_list;
    for (int i = 1; i <=5; i++) {
        bool is_int = false;
        bool need = argv[i].toInt(&is_int);
        if (!is_int)
            return;
        needs_list.append(need);
    }
    QStringList roles = {"defense attorney", "prosecutor", "judge", "jurors", "stenographer"};
    for (int i = 0; i < 5; i++) {
      if (needs_list[i])
        needed_roles.append(roles[i]);
    }
    if (needed_roles.isEmpty())
        return;

    QString message = "=== Case Announcement ===\r\n" + (ooc_name == "" ? current_char : ooc_name) + " needs " + needed_roles.join(", ") + " for " + (case_title == "" ? "a case" : case_title) + "!";

    QList<AOClient*> clients_to_alert;
    // here lies morton, RIP
    QSet<bool> needs_set = needs_list.toSet();
    for (AOClient* client : server->clients) {
        QSet<bool> matches = client->casing_preferences.toSet().intersect(needs_set);
        if (!matches.isEmpty() && !clients_to_alert.contains(client))
            clients_to_alert.append(client);
    }

    for (AOClient* client : clients_to_alert) {
        client->sendPacket(AOPacket("CASEA", {message, argv[1], argv[2], argv[3], argv[4], argv[5], "1"}));
        // you may be thinking, "hey wait a minute the network protocol documentation doesn't mention that last argument!"
        // if you are in fact thinking that, you are correct! it is not in the documentation!
        // however for some inscrutable reason Attorney Online 2 will outright reject a CASEA packet that does not have
        // at least 7 arguments despite only using the first 6. Cera, i kneel. you have truly broken me.
    }
}

void AOClient::sendEvidenceList(AreaData* area)
{
    for (AOClient* client : server->clients) {
        if (client->current_area == current_area)
            client->updateEvidenceList(area);
    }
}

void AOClient::updateEvidenceList(AreaData* area)
{
    QStringList evidence_list;
    QString evidence_format("%1&%2&%3");

    for (AreaData::Evidence evidence : area->evidence()) {
        if (!checkAuth(ACLFlags.value("CM")) && area->eviMod() == AreaData::EvidenceMod::HIDDEN_CM) {
            QRegularExpression regex("<owner=(.*?)>");
            QRegularExpressionMatch match = regex.match(evidence.description);
            if (match.hasMatch()) {
                QStringList owners = match.captured(1).split(",");
                if (!owners.contains("all", Qt::CaseSensitivity::CaseInsensitive) && !owners.contains(pos, Qt::CaseSensitivity::CaseInsensitive)) {
                    continue;
                }
            }
            // no match = show it to all
        }
        evidence_list.append(evidence_format
            .arg(evidence.name)
            .arg(evidence.description)
            .arg(evidence.image));
    }

    sendPacket(AOPacket("LE", evidence_list));
}

AOPacket AOClient::validateIcPacket(AOPacket packet)
{
    // Welcome to the super cursed server-side IC chat validation hell

    // I wanted to use enums or #defines here to make the
    // indicies of the args arrays more readable. But,
    // in typical AO fasion, the indicies for the incoming
    // and outgoing packets are different. Just RTFM.

    AOPacket invalid("INVALID", {});
    QStringList args;
    if (current_char == "" || !joined)
        // Spectators cannot use IC
        return invalid;
    AreaData* area = server->areas[current_area];
    if (area->lockStatus() == AreaData::LockStatus::SPECTATABLE && !area->invited().contains(id) && !checkAuth(ACLFlags.value("BYPASS_LOCKS")))
        // Non-invited players cannot speak in spectatable areas
        return invalid;

    QList<QVariant> incoming_args;
    for (QString arg : packet.contents) {
        incoming_args.append(QVariant(arg));
    }

    // desk modifier
    QStringList allowed_desk_mods;
    allowed_desk_mods << "chat" << "0" << "1" << "2" << "3" << "4" << "5";
    if (allowed_desk_mods.contains(incoming_args[0].toString())) {
        args.append(incoming_args[0].toString());
    }
    else
        return invalid;

    // preanim
    args.append(incoming_args[1].toString());

    // char name
    if (current_char.toLower() != incoming_args[2].toString().toLower()) {
        // Selected char is different from supplied folder name
        // This means the user is INI-swapped
        if (!area->iniswapAllowed()) {
            if (!server->characters.contains(incoming_args[2].toString(), Qt::CaseInsensitive))
                return invalid;
        }
        qDebug() << "INI swap detected from " << getIpid();
    }
    current_iniswap = incoming_args[2].toString();
    args.append(incoming_args[2].toString());

    // emote
    emote = incoming_args[3].toString();
    if (first_person)
        emote = "";
    args.append(emote);

    // message text
    if (incoming_args[4].toString().size() > ConfigManager::maxCharacters())
        return invalid;

    QString incoming_msg = dezalgo(incoming_args[4].toString().trimmed());
    if (!area->lastICMessage().isEmpty()
            && incoming_msg == area->lastICMessage()[4]
            && incoming_msg != "")
        return invalid;

    if (incoming_msg == "" && area->blankpostingAllowed() == false) {
        sendServerMessage("Blankposting has been forbidden in this area.");
        return invalid;
    }

    if (is_gimped) {
        QString gimp_message = ConfigManager::gimpList()[(genRand(1, ConfigManager::gimpList().size() - 1))];
        incoming_msg = gimp_message;
    }

    if (is_shaken) {
        QStringList parts = incoming_msg.split(" ");
        std::random_shuffle(parts.begin(), parts.end());
        incoming_msg = parts.join(" ");
    }

    if (is_disemvoweled) {
        QString disemvoweled_message = incoming_msg.remove(QRegExp("[AEIOUaeiou]"));
        incoming_msg = disemvoweled_message;
    }

    last_message = incoming_msg;
    args.append(incoming_msg);

    // side
    // this is validated clientside so w/e
    args.append(incoming_args[5].toString());
    if (pos != incoming_args[5].toString()) {
        pos = incoming_args[5].toString();
        updateEvidenceList(server->areas[current_area]);
    }

    // sfx name
    args.append(incoming_args[6].toString());

    // emote modifier
    // Now, gather round, y'all. Here is a story that is truly a microcosm of the AO dev experience.
    // If this value is a 4, it will crash the client. Why? Who knows, but it does.
    // Now here is the kicker: in certain versions, the client would incorrectly send a 4 here
    // For a long time, by configuring the client to do a zoom with a preanim, it would send 4
    // This would crash everyone else's client, and the feature had to be disabled
    // But, for some reason, nobody traced the cause of this issue for many many years.
    // The serverside fix is needed to ensure invalid values are not sent, because the client sucks
    int emote_mod = incoming_args[7].toInt();

    if (emote_mod == 4)
        emote_mod = 6;
    if (emote_mod != 0 && emote_mod != 1 && emote_mod != 2 && emote_mod != 5 && emote_mod != 6)
        return invalid;
    args.append(QString::number(emote_mod));

    // char id
    if (incoming_args[8].toInt() != char_id)
        return invalid;
    args.append(incoming_args[8].toString());

    // sfx delay
    args.append(incoming_args[9].toString());

    // objection modifier
    if (incoming_args[10].toString().contains("4")) {
        // custom shout includes text metadata
        args.append(incoming_args[10].toString());
    }
    else {
        int obj_mod = incoming_args[10].toInt();
        if (obj_mod != 0 && obj_mod != 1 && obj_mod != 2 && obj_mod != 3)
            return invalid;
        args.append(QString::number(obj_mod));
    }

    // evidence
    int evi_idx = incoming_args[11].toInt();
    if (evi_idx > area->evidence().length())
        return invalid;
    args.append(QString::number(evi_idx));

    // flipping
    int flip = incoming_args[12].toInt();
    if (flip != 0 && flip != 1)
        return invalid;
    flipping = QString::number(flip);
    args.append(flipping);

    // realization
    int realization = incoming_args[13].toInt();
    if (realization != 0 && realization != 1)
        return invalid;
    args.append(QString::number(realization));

    // text color
    int text_color = incoming_args[14].toInt();
    if (text_color < 0 || text_color > 11)
        return invalid;
    args.append(QString::number(text_color));

    // 2.6 packet extensions
    if (incoming_args.length() > 15) {
        // showname
        QString incoming_showname = dezalgo(incoming_args[15].toString().trimmed());
        if (!(incoming_showname == current_char || incoming_showname.isEmpty()) && !area->shownameAllowed()) {
            sendServerMessage("Shownames are not allowed in this area!");
            return invalid;
        }
        if (incoming_showname.length() > 30) {
            sendServerMessage("Your showname is too long! Please limit it to under 30 characters");
            return invalid;
        }

        // if the raw input is not empty but the trimmed input is, use a single space
        if (incoming_showname.isEmpty() && !incoming_args[15].toString().isEmpty())
            incoming_showname = " ";
        args.append(incoming_showname);
        showname = incoming_showname;

        // other char id
        // things get a bit hairy here
        // don't ask me how this works, because i don't know either
        QStringList pair_data = incoming_args[16].toString().split("^");
        pairing_with = pair_data[0].toInt();
        QString front_back = "";
        if (pair_data.length() > 1)
            front_back = "^" + pair_data[1];
        int other_charid = pairing_with;
        bool pairing = false;
        QString other_name = "0";
        QString other_emote = "0";
        QString other_offset = "0";
        QString other_flip = "0";
        for (AOClient* client : server->clients) {
            if (client->pairing_with == char_id
                    && other_charid != char_id
                    && client->char_id == pairing_with
                    && client->pos == pos) {
                other_name = client->current_iniswap;
                other_emote = client->emote;
                other_offset = client->offset;
                other_flip = client->flipping;
                pairing = true;
            }
        }
        if (!pairing) {
            other_charid = -1;
            front_back = "";
        }
        args.append(QString::number(other_charid) + front_back);
        args.append(other_name);
        args.append(other_emote);

        // self offset
        offset = incoming_args[17].toString();
        // versions 2.6-2.8 cannot validate y-offset so we send them just the x-offset
        if ((version.release == 2) && (version.major == 6 || version.major == 7 || version.major == 8)) {
            QString x_offset = offset.split("&")[0];
            args.append(x_offset);
            QString other_x_offset = other_offset.split("&")[0];
            args.append(other_x_offset);
        }
        else {
            args.append(offset);
            args.append(other_offset);
        }
        args.append(other_flip);

        // immediate text processing
        int immediate = incoming_args[18].toInt();
        if (area->forceImmediate()) {
            if (args[7] == "1" || args[7] == "2") {
                args[7] = "0";
                immediate = 1;
            }
            else if (args[7] == "6") {
                args[7] = "5";
                immediate = 1;
            }
        }
        if (immediate != 1 && immediate != 0)
            return invalid;
        args.append(QString::number(immediate));
    }

    // 2.8 packet extensions
    if (incoming_args.length() > 19) {
        // sfx looping
        int sfx_loop = incoming_args[19].toInt();
        if (sfx_loop != 0 && sfx_loop != 1)
            return invalid;
        args.append(QString::number(sfx_loop));

        // screenshake
        int screenshake = incoming_args[20].toInt();
        if (screenshake != 0 && screenshake != 1)
            return invalid;
        args.append(QString::number(screenshake));

        // frames shake
        args.append(incoming_args[21].toString());

        // frames realization
        args.append(incoming_args[22].toString());

        // frames sfx
        args.append(incoming_args[23].toString());

        // additive
        int additive = incoming_args[24].toInt();
        if (additive != 0 && additive != 1)
            return invalid;
        else if (area->lastICMessage().isEmpty()){
            additive = 0;
        }
        else if (!(char_id == area->lastICMessage()[8].toInt())) {
            additive = 0;
        }
        else if (additive == 1) {
            args[4].insert(0, " ");
        }
        args.append(QString::number(additive));

        // effect
        args.append(incoming_args[25].toString());
    }

    //Testimony playback
    if (area->testimonyRecording() == AreaData::TestimonyRecording::RECORDING || area->testimonyRecording() == AreaData::TestimonyRecording::ADD) {
        if (args[5] != "wit")
            return AOPacket("MS", args);

        if (area->statement() == -1) {
            args[4] = "~~\\n-- " + args[4] + " --";
            args[14] = "3";
            server->broadcast(AOPacket("RT",{"testimony1"}), current_area);
        }
        addStatement(args);
    }
    else if (area->testimonyRecording() == AreaData::TestimonyRecording::UPDATE) {
        args = updateStatement(args);
    }
    else if (area->testimonyRecording() == AreaData::TestimonyRecording::PLAYBACK) {
        AreaData::TestimonyProgress l_progress;

        if (args[4] == ">") {
            pos = "wit";
            auto l_statement = area->jumpToStatement(area->statement() +1);
            args = l_statement.first;
            l_progress = l_statement.second;

            if (l_progress == AreaData::TestimonyProgress::LOOPED) {
                sendServerMessageArea("Last statement reached. Looping to first statement.");
            }
        }
        if (args[4] == "<") {
            pos = "wit";
            auto l_statement = area->jumpToStatement(area->statement() - 1);
            args = l_statement.first;
            l_progress = l_statement.second;

            if (l_progress == AreaData::TestimonyProgress::STAYED_AT_FIRST) {
                sendServerMessage("First statement reached.");
            }
        }

        QString decoded_message = decodeMessage(args[4]); //Get rid of that pesky encoding first.
        QRegularExpression jump("(?<arrow>>)(?<int>[0,1,2,3,4,5,6,7,8,9]+)");
        QRegularExpressionMatch match = jump.match(decoded_message);
        if (match.hasMatch()) {
            pos = "wit";
            auto l_statement = area->jumpToStatement(match.captured("int").toInt());
            args = l_statement.first;
            l_progress = l_statement.second;


            switch (l_progress){
            case AreaData::TestimonyProgress::LOOPED:
            {
                sendServerMessageArea("Last statement reached. Looping to first statement.");
            }
            case AreaData::TestimonyProgress::STAYED_AT_FIRST:
            {
                sendServerMessage("First statement reached.");
            }
            case AreaData::TestimonyProgress::OK:
            default:
                // No need to handle.
                break;
            }
        }
    }

    return AOPacket("MS", args);
}

QString AOClient::dezalgo(QString p_text)
{
    QRegularExpression rxp("([̴̵̶̷̸̡̢̧̨̛̖̗̘̙̜̝̞̟̠̣̤̥̦̩̪̫̬̭̮̯̰̱̲̳̹̺̻̼͇͈͉͍͎̀́̂̃̄̅̆̇̈̉̊̋̌̍̎̏̐̑̒̓̔̽̾̿̀́͂̓̈́͆͊͋͌̕̚ͅ͏͓͔͕͖͙͚͐͑͒͗͛ͣͤͥͦͧͨͩͪͫͬͭͮͯ͘͜͟͢͝͞͠͡])");
    QString filtered = p_text.replace(rxp, "");
    return filtered;
}

bool AOClient::checkEvidenceAccess(AreaData *area)
{
    switch(area->eviMod()) {
    case AreaData::EvidenceMod::FFA:
        return true;
    case AreaData::EvidenceMod::CM:
    case AreaData::EvidenceMod::HIDDEN_CM:
        return checkAuth(ACLFlags.value("CM"));
    case AreaData::EvidenceMod::MOD:
        return authenticated;
    default:
        return false;
    }
}

void AOClient::updateJudgeLog(AreaData* area, AOClient* client, QString action)
{
    QString timestamp = QTime::currentTime().toString("hh:mm:ss");
    QString uid = QString::number(client->id);
    QString char_name = client->current_char;
    QString ipid = client->getIpid();
    QString message = action;
    QString logmessage = QString("[%1]: [%2] %3 (%4) %5").arg(timestamp, uid, char_name, ipid, message);
    area->appendJudgelog(logmessage);
}

QString AOClient::decodeMessage(QString incoming_message)
{
   QString decoded_message = incoming_message.replace("<num>", "#")
                                             .replace("<percent>", "%")
                                             .replace("<dollar>", "$")
                                             .replace("<and>", "&");
    return decoded_message;
}

void AOClient::loginAttempt(QString message)
{
    switch (ConfigManager::authType()) {
    case DataTypes::AuthType::SIMPLE:
        if (message == ConfigManager::modpass()) {
            sendPacket("AUTH", {"1"}); // Client: "You were granted the Disable Modcalls button."
            sendServerMessage("Logged in as a moderator."); // pre-2.9.1 clients are hardcoded to display the mod UI when this string is sent in OOC
            authenticated = true;
        }
        else {
            sendPacket("AUTH", {"0"}); // Client: "Login unsuccessful."
            sendServerMessage("Incorrect password.");
        }
        server->areas.value(current_area)->logLogin(current_char, ipid, authenticated, "moderator");
        break;
    case DataTypes::AuthType::ADVANCED:
        QStringList login = message.split(" ");
        if (login.size() < 2) {
            sendServerMessage("You must specify a username and a password");
            sendServerMessage("Exiting login prompt.");
            is_logging_in = false;
            return;
        }
        QString username = login[0];
        QString password = login[1];
        if (server->db_manager->authenticate(username, password)) {
            moderator_name = username;
            authenticated = true;
            sendPacket("AUTH", {"1"}); // Client: "You were granted the Disable Modcalls button."
            if (version.release <= 2 && version.major <= 9 && version.minor <= 0)
                sendServerMessage("Logged in as a moderator."); // pre-2.9.1 clients are hardcoded to display the mod UI when this string is sent in OOC
            sendServerMessage("Welcome, " + username);
        }
        else {
            sendPacket("AUTH", {"0"}); // Client: "Login unsuccessful."
            sendServerMessage("Incorrect password.");
        }
        server->areas.value(current_area)->logLogin(current_char, ipid, authenticated, username);
        break;
    }
    sendServerMessage("Exiting login prompt.");
    is_logging_in = false;
    return;
}

