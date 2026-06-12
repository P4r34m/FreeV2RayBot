<?php

namespace App\Telegram\Handlers;

use App\Models\BotUser;
use App\Services\BotUserService;
use App\Telegram\Content;
use App\Telegram\Handlers\Admin\AdminMenuHandler;
use App\Telegram\Keyboards;
use SergiX44\Nutgram\Nutgram;

/**
 * In reply-keyboard mode the main menu is a persistent keyboard whose buttons
 * arrive as plain text. This routes that text to the same handlers the inline
 * buttons would call. No-op in inline mode or for non-menu text.
 */
class ReplyKeyboardRouter
{
    public function __invoke(Nutgram $bot): void
    {
        if (Keyboards::mode() !== 'reply') {
            return;
        }

        $text = trim($bot->message()?->text ?? '');
        if ($text === '' || str_starts_with($text, '/')) {
            return; // commands are handled by their own handlers
        }

        $map = [
            Content::buttonLabel('menu.get_config') => GetConfigHandler::class,
            Content::buttonLabel('menu.tutorials') => TutorialsHandler::class,
            Content::buttonLabel('menu.referral') => ReferralHandler::class,
            Content::buttonLabel('menu.profile') => ProfileHandler::class,
            Content::buttonLabel('menu.admin') => AdminMenuHandler::class,
        ];

        $handlerClass = $map[$text] ?? null;
        if ($handlerClass === null) {
            return;
        }

        // botUser is set by ResolveBotUser when run in the group; resolve defensively otherwise.
        $user = $bot->get('botUser');
        if (! $user instanceof BotUser) {
            $user = app(BotUserService::class)->resolve($bot);
            $bot->set('botUser', $user);
        }

        if ($user->isAccessBlocked()) {
            return;
        }

        if ($handlerClass === AdminMenuHandler::class && ! $user->is_admin) {
            return;
        }

        app($handlerClass)($bot);
    }
}
