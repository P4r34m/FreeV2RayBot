<?php

namespace App\Telegram\Handlers\Admin;

use App\Enums\PanelType;
use App\Models\Panel;
use App\Panels\PanelManager;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Apply a target selection (admin:panels:tgt:{combo}, combo = "{panelId}_{index}").
 * 3x-ui = single inbound; Remnawave/PasarGuard = toggle squad/group membership.
 */
class AdminPanelSetTargetHandler
{
    public function __invoke(Nutgram $bot, string $combo): void
    {
        [$panelId, $index] = array_pad(explode('_', $combo, 2), 2, null);

        $panel = Panel::find((int) $panelId);
        if (! $panel || ! is_numeric($index)) {
            Reply::toast($bot, 'نامعتبر', alert: true);

            return;
        }

        try {
            $targets = app(PanelManager::class)->driver($panel)->listTargets();
        } catch (Throwable) {
            $targets = [];
        }

        $target = $targets[(int) $index] ?? null;
        if ($target === null) {
            Reply::toast($bot, 'لیست منقضی شد، دوباره تلاش کنید', alert: true);
            (new AdminPanelTargetsHandler)($bot, (string) $panel->id);

            return;
        }

        $settings = $panel->settings ?? [];

        $key = match ($panel->type) {
            PanelType::ThreeXui => 'inbound_ids',
            PanelType::Remnawave => 'squad_uuids',
            PanelType::PasarGuard => 'group_ids',
        };
        $intKey = $panel->type !== PanelType::Remnawave; // inbound_ids & group_ids are ints

        // Seed 3x-ui from the legacy single inbound_id on the first multi-toggle.
        $existing = $settings[$key] ?? ($panel->type === PanelType::ThreeXui && isset($settings['inbound_id'])
            ? [$settings['inbound_id']]
            : []);
        $current = array_map('strval', $existing);
        $id = (string) $target['id'];

        $current = in_array($id, $current, true)
            ? array_values(array_diff($current, [$id]))
            : [...$current, $id];

        $settings[$key] = $intKey
            ? array_values(array_map('intval', $current))
            : array_values($current);

        // Keep the legacy single inbound_id in sync (first selected) for back-compat.
        if ($panel->type === PanelType::ThreeXui) {
            if ($settings['inbound_ids'] !== []) {
                $settings['inbound_id'] = $settings['inbound_ids'][0];
            } else {
                unset($settings['inbound_id']);
            }
        }

        $panel->update(['settings' => $settings]);

        Reply::toast($bot, 'به‌روزرسانی شد');
        (new AdminPanelTargetsHandler)($bot, (string) $panel->id);
    }
}
