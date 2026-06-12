<?php

namespace App\Telegram;

use App\Models\Setting;
use App\Support\SettingKey;
use Illuminate\Support\Collection;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;

/**
 * Inline keyboards. Button LABELS come from the admin-editable Content store
 * (with optional premium-emoji icons); callback_data is structural and stays here.
 */
class Keyboards
{
    public const CB_MENU = 'menu';

    public const CB_GET_CONFIG = 'get_config';

    public const CB_CONFIG_NEW = 'config:new';

    public const CB_CONFIG_RENEW = 'config:renew';

    public const CB_CONFIG_STATUS = 'config:status';

    public const CB_TUTORIALS = 'tutorials';

    public const CB_REFERRAL = 'referral';

    public const CB_PROFILE = 'profile';

    public const CB_PROFILE_HISTORY = 'profile:history';

    public const CB_CHECK_JOIN = 'check_join';

    public const CB_ADMIN = 'admin';

    /** Current main-menu button style: 'inline' (glass) or 'reply' (keyboard). */
    public static function mode(): string
    {
        return Setting::string(SettingKey::KEYBOARD_MODE, 'inline') === 'reply' ? 'reply' : 'inline';
    }

    /**
     * Main menu as a persistent reply keyboard. Button presses arrive as text and
     * are routed by ReplyKeyboardRouter. (Premium-emoji icons are inline-only.)
     */
    public static function mainReplyKeyboard(bool $isAdmin = false): ReplyKeyboardMarkup
    {
        $kb = ReplyKeyboardMarkup::make(resize_keyboard: true, is_persistent: true)
            ->addRow(KeyboardButton::make(Content::buttonLabel('menu.get_config')))
            ->addRow(
                KeyboardButton::make(Content::buttonLabel('menu.tutorials')),
                KeyboardButton::make(Content::buttonLabel('menu.referral')),
            )
            ->addRow(KeyboardButton::make(Content::buttonLabel('menu.profile')));

        if ($isAdmin) {
            $kb->addRow(KeyboardButton::make(Content::buttonLabel('menu.admin')));
        }

        return $kb;
    }

    /** The user-facing main menu (+ admin entry when applicable). */
    public static function mainMenu(bool $isAdmin = false): InlineKeyboardMarkup
    {
        $kb = InlineKeyboardMarkup::make()
            ->addRow(Content::button('menu.get_config', self::CB_GET_CONFIG))
            ->addRow(
                Content::button('menu.tutorials', self::CB_TUTORIALS),
                Content::button('menu.referral', self::CB_REFERRAL),
            )
            ->addRow(Content::button('menu.profile', self::CB_PROFILE));

        if ($isAdmin) {
            $kb->addRow(Content::button('menu.admin', self::CB_ADMIN));
        }

        return $kb;
    }

    /** Choose between a brand-new config and renewing the existing one. */
    public static function configMenu(bool $hasActive): InlineKeyboardMarkup
    {
        $kb = InlineKeyboardMarkup::make()
            ->addRow(Content::button('config.new', self::CB_CONFIG_NEW));

        if ($hasActive) {
            $kb->addRow(Content::button('config.renew', self::CB_CONFIG_RENEW));
            $kb->addRow(Content::button('config.status', self::CB_CONFIG_STATUS));
        }

        $kb->addRow(self::backButton());

        return $kb;
    }

    /** Join buttons for every required channel + an "I joined" re-check button. */
    public static function joinChannels(Collection $channels): InlineKeyboardMarkup
    {
        $kb = InlineKeyboardMarkup::make();

        foreach ($channels as $channel) {
            $url = $channel->joinUrl();
            if ($url !== '') {
                $kb->addRow(Btn::make('🔗 '.$channel->title, url: $url));
            }
        }

        $kb->addRow(Content::button('channel.joined', self::CB_CHECK_JOIN));

        return $kb;
    }

    public static function backButton(string $callbackData = self::CB_MENU): Btn
    {
        return Content::button('common.back', $callbackData);
    }

    /** Single "back to menu" keyboard used after async results. */
    public static function backMenu(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()->addRow(Content::button('common.back_menu', self::CB_MENU));
    }

    public static function single(string $contentKey, string $callbackData): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()->addRow(Content::button($contentKey, $callbackData));
    }
}
