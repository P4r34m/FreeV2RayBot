<?php

namespace App\Services;

use App\Models\RequiredChannel;
use App\Models\Setting;
use App\Support\SettingKey;
use Illuminate\Support\Collection;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Force-join: checks whether a user is a member of every required channel.
 * Verification errors (e.g. bot not admin in a channel) fail OPEN and are
 * logged, so a misconfigured channel never bricks the whole bot.
 */
class ChannelLockService
{
    public function enabled(): bool
    {
        return Setting::bool(SettingKey::CHANNEL_LOCK_ENABLED, false)
            && $this->channels()->isNotEmpty();
    }

    /** @return Collection<int, RequiredChannel> */
    public function channels(): Collection
    {
        return RequiredChannel::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Channels the user has NOT joined yet.
     *
     * @return Collection<int, RequiredChannel>
     */
    public function missingChannels(Nutgram $bot, int $telegramId): Collection
    {
        return $this->channels()->filter(
            fn (RequiredChannel $channel) => ! $this->isMember($bot, $channel, $telegramId)
        )->values();
    }

    public function passes(Nutgram $bot, int $telegramId): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        return $this->missingChannels($bot, $telegramId)->isEmpty();
    }

    protected function isMember(Nutgram $bot, RequiredChannel $channel, int $telegramId): bool
    {
        try {
            $member = $bot->getChatMember(
                chat_id: $channel->chat_id,
                user_id: $telegramId,
            );

            $status = $member?->status?->value ?? (string) ($member?->status ?? '');

            return in_array($status, ['creator', 'administrator', 'member'], true);
        } catch (Throwable $e) {
            report($e);

            // Can't verify (bot likely not admin in the channel) => fail open.
            return true;
        }
    }
}
