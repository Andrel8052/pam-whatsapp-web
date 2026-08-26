<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

enum MessageType: int
{
    case Album = 1;
    case Audio = 2;
    case BroadcastNotification = 3;
    case ButtonsResponse = 4;
    case CallLog = 5;
    case Ciphertext = 6;
    case ContactCard = 7;
    case ContactCardMulti = 8;
    case Debug = 9;
    case Document = 10;
    case E2eNotification = 11;
    case GroupProtocol = 12;
    case GroupInvite = 13;
    case GroupNotification = 14;
    case HighlyStructured = 15;
    case Image = 16;
    case Interactive = 17;
    case List = 18;
    case ListResponse = 19;
    case Location = 20;
    case NativeFlow = 21;
    case Notification = 22;
    case NotificationTemplate = 23;
    case Order = 24;
    case Oversized = 25;
    case Payment = 26;
    case PollCreation = 27;
    case Product = 28;
    case Protocol = 29;
    case Reaction = 30;
    case Revoked = 31;
    case ScheduledEventCreation = 32;
    case Sticker = 33;
    case TemplateButtonReply = 34;
    case Text = 35;
    case Unknown = 36;
    case Video = 37;
    case Voice = 38;
}
