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

        if ($panel->type === PanelType::ThreeXui) {
            $settings['inbound_id'] = (int) $target['id'];
            $panel->update(['settings' => $settings]);

            Reply::toast($bot, 'اینباند انتخاب شد ✅');
            (new AdminPanelConfigHandler)($bot, (string) $panel->id);

            return;
        }

        // Multi-select: toggle membership in the array.
        $key = $panel->type === PanelType::Remnawave ? 'squad_uuids' : 'group_ids';
        $current = array_map('strval', $settings[$key] ?? []);
        $id = (string) $target['id'];

        $current = in_array($id, $current, true)
            ? array_values(array_diff($current, [$id]))
            : [...$current, $id];

        // PasarGuard group ids are integers; Remnawave squad uuids stay strings.
        $settings[$key] = $panel->type === PanelType::PasarGuard
            ? array_values(array_map('intval', $current))
            : array_values($current);

        $panel->update(['settings' => $settings]);

        Reply::toast($bot, 'به‌روزرسانی شد');
        (new AdminPanelTargetsHandler)($bot, (string) $panel->id);
    }
}
