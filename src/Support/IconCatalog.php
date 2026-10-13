<?php

namespace Elite\TableViews\Support;

use Filament\Support\Icons\Heroicon;

/**
 * Every icon key the plugin knows, with its default heroicon (`Heroicon` enum). The stored value is the key; the alias
 * `table-views::icons.<key>` resolves it at render time, so an app can remap any of them (for example to Tabler).
 */
class IconCatalog
{
    /** @var array<string, Heroicon> */
    public const ICONS = [
        'star' => Heroicon::Star,
        'heart' => Heroicon::Heart,
        'flag' => Heroicon::Flag,
        'bookmark' => Heroicon::Bookmark,
        'fire' => Heroicon::Fire,
        'bolt' => Heroicon::Bolt,
        'sparkles' => Heroicon::Sparkles,
        'trophy' => Heroicon::Trophy,
        'rocket' => Heroicon::RocketLaunch,
        'light-bulb' => Heroicon::LightBulb,
        'thumb-up' => Heroicon::HandThumbUp,
        'thumb-down' => Heroicon::HandThumbDown,
        'smile' => Heroicon::FaceSmile,
        'check-circle' => Heroicon::CheckCircle,
        'x-circle' => Heroicon::XCircle,
        'warning' => Heroicon::ExclamationTriangle,
        'exclamation-circle' => Heroicon::ExclamationCircle,
        'info' => Heroicon::InformationCircle,
        'question' => Heroicon::QuestionMarkCircle,
        'no-symbol' => Heroicon::NoSymbol,
        'shield-check' => Heroicon::ShieldCheck,
        'shield-exclamation' => Heroicon::ShieldExclamation,
        'lock' => Heroicon::LockClosed,
        'unlock' => Heroicon::LockOpen,
        'user' => Heroicon::User,
        'users' => Heroicon::Users,
        'user-circle' => Heroicon::UserCircle,
        'user-group' => Heroicon::UserGroup,
        'user-plus' => Heroicon::UserPlus,
        'identification' => Heroicon::Identification,
        'academic-cap' => Heroicon::AcademicCap,
        'currency' => Heroicon::Banknotes,
        'currency-dollar' => Heroicon::CurrencyDollar,
        'credit-card' => Heroicon::CreditCard,
        'wallet' => Heroicon::Wallet,
        'receipt' => Heroicon::ReceiptPercent,
        'shopping-cart' => Heroicon::ShoppingCart,
        'shopping-bag' => Heroicon::ShoppingBag,
        'store' => Heroicon::BuildingStorefront,
        'gift' => Heroicon::Gift,
        'ticket' => Heroicon::Ticket,
        'scale' => Heroicon::Scale,
        'calculator' => Heroicon::Calculator,
        'briefcase' => Heroicon::Briefcase,
        'bank' => Heroicon::BuildingLibrary,
        'cube' => Heroicon::Cube,
        'truck' => Heroicon::Truck,
        'clock' => Heroicon::Clock,
        'calendar' => Heroicon::Calendar,
        'calendar-days' => Heroicon::CalendarDays,
        'bell' => Heroicon::Bell,
        'bell-alert' => Heroicon::BellAlert,
        'refresh' => Heroicon::ArrowPath,
        'play' => Heroicon::Play,
        'document' => Heroicon::DocumentText,
        'document-duplicate' => Heroicon::DocumentDuplicate,
        'document-check' => Heroicon::DocumentCheck,
        'document-search' => Heroicon::DocumentMagnifyingGlass,
        'clipboard' => Heroicon::Clipboard,
        'clipboard-list' => Heroicon::ClipboardDocumentList,
        'clipboard-check' => Heroicon::ClipboardDocumentCheck,
        'newspaper' => Heroicon::Newspaper,
        'book' => Heroicon::BookOpen,
        'paperclip' => Heroicon::PaperClip,
        'pencil' => Heroicon::Pencil,
        'edit' => Heroicon::PencilSquare,
        'folder' => Heroicon::Folder,
        'archive' => Heroicon::ArchiveBox,
        'inbox' => Heroicon::Inbox,
        'printer' => Heroicon::Printer,
        'envelope' => Heroicon::Envelope,
        'chat' => Heroicon::ChatBubbleLeft,
        'chat-bubbles' => Heroicon::ChatBubbleLeftRight,
        'phone' => Heroicon::Phone,
        'paper-airplane' => Heroicon::PaperAirplane,
        'megaphone' => Heroicon::Megaphone,
        'share' => Heroicon::Share,
        'link' => Heroicon::Link,
        'map-pin' => Heroicon::MapPin,
        'map' => Heroicon::Map,
        'globe' => Heroicon::GlobeAlt,
        'globe-region' => Heroicon::GlobeEuropeAfrica,
        'building-office' => Heroicon::BuildingOffice,
        'building-office-2' => Heroicon::BuildingOffice2,
        'home' => Heroicon::Home,
        'chart-bar' => Heroicon::ChartBar,
        'chart-pie' => Heroicon::ChartPie,
        'chart-line' => Heroicon::PresentationChartLine,
        'trending-up' => Heroicon::ArrowTrendingUp,
        'trending-down' => Heroicon::ArrowTrendingDown,
        'table' => Heroicon::TableCells,
        'tag' => Heroicon::Tag,
        'funnel' => Heroicon::Funnel,
        'search' => Heroicon::MagnifyingGlass,
        'eye' => Heroicon::Eye,
        'eye-slash' => Heroicon::EyeSlash,
        'list' => Heroicon::ListBullet,
        'queue' => Heroicon::QueueList,
        'squares' => Heroicon::Squares2x2,
        'stack' => Heroicon::RectangleStack,
        'adjustments' => Heroicon::AdjustmentsHorizontal,
        'arrow-up' => Heroicon::ArrowUp,
        'arrow-down' => Heroicon::ArrowDown,
        'arrow-left' => Heroicon::ArrowLeft,
        'arrow-right' => Heroicon::ArrowRight,
        'swap' => Heroicon::ArrowsRightLeft,
        'undo' => Heroicon::ArrowUturnLeft,
        'download' => Heroicon::ArrowDownTray,
        'upload' => Heroicon::ArrowUpTray,
        'cog' => Heroicon::Cog6Tooth,
        'wrench' => Heroicon::WrenchScrewdriver,
        'key' => Heroicon::Key,
        'cloud' => Heroicon::Cloud,
        'code' => Heroicon::CodeBracket,
        'sun' => Heroicon::Sun,
        'moon' => Heroicon::Moon,
        'camera' => Heroicon::Camera,
        'photo' => Heroicon::Photo,
        'language' => Heroicon::Language,
        'fingerprint' => Heroicon::FingerPrint,
        'plus-circle' => Heroicon::PlusCircle,
        'minus-circle' => Heroicon::MinusCircle,
        'trash' => Heroicon::Trash,
    ];

    /**
     * @return array<string, Heroicon> alias => default icon
     */
    public static function aliases(): array
    {
        $aliases = [];

        foreach (static::ICONS as $key => $icon) {
            $aliases["table-views::icons.{$key}"] = $icon;
        }

        return $aliases;
    }
}
