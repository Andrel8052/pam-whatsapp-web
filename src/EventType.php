<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum EventType: int
{
    case QrCode = 1;
    case Authenticated = 2;
    case Ready = 3;
    case MessageReceived = 4;
    case Disconnected = 5;
    case Error = 6;
    case AuthenticationFailure = 7;
    case CallReceived = 8;
    case BatteryChanged = 9;
    case StateChanged = 10;
    case ChatArchived = 11;
    case ChatRemoved = 12;
    case PairingCodeReceived = 13;
    case ContactChanged = 14;
    case GroupAdminChanged = 15;
    case GroupJoined = 16;
    case GroupLeft = 17;
    case GroupMembershipRequest = 18;
    case GroupUpdated = 19;
    case LoadingScreen = 20;
    case MediaUploaded = 21;
    case MessageAcknowledged = 22;
    case MessageCiphertext = 23;
    case MessageCiphertextFailed = 24;
    case MessageCreated = 25;
    case MessageEdited = 26;
    case MessageReaction = 27;
    case MessageRevokedEveryone = 28;
    case MessageRevokedMe = 29;
    case RemoteSessionSaved = 30;
    case UnreadCountChanged = 31;
    case VoteUpdated = 32;
}
