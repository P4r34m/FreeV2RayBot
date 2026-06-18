<?php

namespace App\Telegram;

/**
 * Single source of truth for every user-facing string. Used to seed the
 * editable bot_texts/bot_buttons tables AND as the runtime fallback when a key
 * is missing. Texts are HTML and may embed premium emoji via
 * <tg-emoji emoji-id="123">⭐️</tg-emoji>. {placeholders} are substituted at render.
 */
final class ContentDefaults
{
    /** @return array<string, string> key => HTML content */
    public static function texts(): array
    {
        return [
            'welcome' => "به ربات کانفیگ رایگان خوش آمدید 🌐\nبرای دریافت کانفیگ از دکمه‌های زیر استفاده کنید.",
            'welcome_referred' => "به ربات کانفیگ رایگان خوش آمدید 🌐\nدقت کنید تا زمانی که کانفیگ نگرفته باشید به‌عنوان رفرال حساب نمی‌شوید!\nبرای دریافت کانفیگ از دکمه‌های زیر استفاده کنید.",

            'config.menu_active' => "🎁 <b>دریافت کانفیگ</b>\n\nشما یک کانفیگ فعال دارید. می‌توانید کانفیگ جدید بگیرید، کانفیگ فعلی را تمدید کنید، یا وضعیت آن را ببینید.",
            'config.creating' =>"⏳ در حال ساخت کانفیگ شما...\nچند لحظه صبر کنید، لینک برایتان ارسال می‌شود.",
            'config.renewing' => "♻️ در حال تمدید کانفیگ شما...\nچند لحظه صبر کنید.",
            'config.max_reached' => "شما در حال حاضر کانفیگ فعال دارید (حداکثر مجاز: {max}).\nمی‌توانید کانفیگ فعلی را تمدید کنید.",
            'config.none_to_renew' => 'کانفیگ فعالی برای تمدید ندارید. ابتدا یک کانفیگ جدید بسازید.',
            'config.renew_not_expired' => '♻️ اشتراک رایگان شما هنوز منقضی نشده است. تمدید فقط پس از پایان زمان امکان‌پذیر است.',
            'config.free_not_expired' => '⏳ اشتراک رایگان فعلی شما هنوز منقضی نشده است. کانفیگ رایگان جدید فقط پس از پایان زمان اشتراک فعلی ساخته می‌شود.',
            'config.caption' => "✅ <b>کانفیگ شما آماده شد!</b>\n\n📦 حجم: <b>{limit}</b>\n⏳ انقضا: <b>{expiry}</b>\n\n🔗 <b>لینک اشتراک (Subscription):</b>\n<code>{url}</code>\n\n👇 لینک بالا را کپی کرده و در برنامه‌ی خود وارد کنید. برای راهنما، دکمه‌ی «آموزش‌ها» را بزنید.",
            'config.caption_links' => "✅ <b>کانفیگ شما آماده شد!</b>\n\n📦 حجم: <b>{limit}</b>\n⏳ انقضا: <b>{expiry}</b>\n\n🔗 <b>کانفیگ‌ها (هر خط را جداگانه وارد کنید):</b>\n{links}",
            'config.error' => '❌ متأسفانه ساخت کانفیگ با خطا روبه‌رو شد. لطفاً کمی بعد دوباره تلاش کنید یا با پشتیبانی در تماس باشید.',
            'config.no_panel' => '⚠️ {message} لطفاً بعداً تلاش کنید.',
            'config.pick_server' => "🌐 <b>انتخاب سرور</b>\n\nکانفیگ شما از کدام سرور ساخته شود؟ یکی از سرورهای زیر را انتخاب کنید:",
            'config.list_header' => "🔑 <b>اشتراک‌های فعال شما</b>\n\nبرای دیدن جزئیات هر اشتراک، روی آن بزنید:",
            'config.rotated' => '🔄 لینک اشتراک شما با موفقیت عوض شد. لینک قبلی دیگر کار نمی‌کند.',
            'config.single_header' => "📥 <b>کانفیگ‌های تکی</b>\nهر خط را جداگانه در برنامه‌ی خود وارد کنید:\n\n{links}",
            'config.single_empty' => '⚠️ دریافت کانفیگ‌های تکی ممکن نشد. لطفاً از لینک اشتراک استفاده کنید یا کمی بعد دوباره تلاش کنید.',
            'config.rotate_failed' => '⚠️ تعویض لینک اشتراک انجام نشد. لطفاً کمی بعد دوباره تلاش کنید.',

            'account.status' => "📊 <b>وضعیت کانفیگ شما</b>\n\n🟢 وضعیت: <b>{status}</b>\n📥 مصرف‌شده: <b>{used}</b> از {limit}\n📦 باقیمانده: <b>{remaining}</b>\n⏳ انقضا: <b>{expiry}</b>\n\n🔗 <code>{url}</code>",
            'account.no_config' => "شما در حال حاضر کانفیگ فعالی ندارید.\nبرای دریافت، دکمه‌ی «دریافت کانفیگ جدید» را بزنید.",

            'profile.body' => "👤 <b>پروفایل شما</b>\n\n🆔 آیدی عددی: <code>{id}</code>\n👋 نام: {name}\n📅 عضویت: {joined}\n\n🔑 کانفیگ‌های ساخته‌شده: <b>{configs}</b>\n🟢 کانفیگ فعال: <b>{active}</b>\n👥 زیرمجموعه‌ها: <b>{referrals}</b>\n🪙 سکه: <b>{coins}</b>\n🎁 هدیه: <b>{bonus_traffic}</b> و <b>{bonus_days}</b> روز",
            'profile.history_header' => '🗂 <b>تاریخچه‌ی کانفیگ‌های شما</b>',
            'profile.history_empty' => 'هنوز کانفیگی نساخته‌اید.',

            'referral.body' => "👥 <b>زیرمجموعه‌گیری</b>\n\nبا دعوت دوستان خود حجم و زمان هدیه بگیرید 🎁\n\n🔗 <b>لینک دعوت شما:</b>\n<code>{link}</code>\n\n👤 تعداد زیرمجموعه‌های تأییدشده: <b>{count}</b>\n🎁 هدیه‌ی موجود: <b>{bonus_traffic}</b> حجم و <b>{bonus_days}</b> روز\n\n{rules}",
            'referral.disabled' => 'سیستم زیرمجموعه‌گیری در حال حاضر غیرفعال است.',
            'referral.coin_body' => "👥 <b>دعوت دوستان</b>\n\nبا دعوت هر دوست سکه بگیرید و با سکه‌ها اشتراک تهیه کنید! 🪙\n\n🪙 موجودی سکه شما: <b>{coins}</b>\n👤 دعوت‌های تأییدشده: <b>{count}</b>\n\n🔗 لینک دعوت شما:\n<code>{link}</code>\n\n{rules}",

            'coin.store_header' => "🛒 <b>فروشگاه سکه‌ای</b>\n\n🪙 موجودی شما: <b>{coins}</b> سکه\n\nیکی از بسته‌ها را برای خرید انتخاب کنید:",
            'coin.store_empty' => 'هنوز بسته‌ای برای خرید تعریف نشده است.',
            'coin.disabled' => 'فروشگاه سکه‌ای در حال حاضر فعال نیست.',
            'coin.plan_body' => "🛒 <b>{name}</b>\n\n📦 حجم: <b>{volume}</b>\n⏳ مدت: <b>{duration}</b>\n🪙 قیمت: <b>{price}</b> سکه\n\n🪙 موجودی شما: <b>{coins}</b> سکه\n\nنحوه‌ی اعمال را انتخاب کنید:",
            'coin.insufficient' => '🪙 سکه کافی ندارید. موجودی: {coins} سکه — قیمت: {price} سکه.',
            'coin.pick_config' => 'روی کدام اشتراک اعمال شود؟',
            'coin.no_configs' => 'اشتراکی برای افزودن ندارید. ابتدا «کانفیگ جدید» را انتخاب کنید.',
            'coin.extended' => '✅ خرید موفق! بسته به اشتراک شما اضافه شد.',
            'coin.failed' => '⚠️ خرید ناموفق بود. اگر سکه‌ای کسر شده، بازگردانده شد. کمی بعد دوباره تلاش کنید.',
            'referral.share_text' => 'با این ربات کانفیگ رایگان بگیر! 🎁',
            'referral.notify' => "🎉 یک زیرمجموعه‌ی جدید تأیید شد و هدیه‌ی شما اعمال شد!",
            'referral.notify_coin' => "🎉 یک زیرمجموعه‌ی جدید تأیید شد و <b>{coins}</b> سکه گرفتید! 🪙",

            'tutorials.empty' => '📚 هنوز آموزشی ثبت نشده است. به‌زودی اضافه می‌شود.',
            'tutorials.header' => "📚 <b>آموزش‌ها</b>\n\nیک مورد را برای مشاهده انتخاب کنید:",
            'tutorials.unavailable' => 'این آموزش در دسترس نیست.',

            'channel.lock_prompt' => "🔒 برای استفاده از ربات، ابتدا در کانال‌های زیر عضو شوید و سپس روی «✅ عضو شدم» بزنید:",
            'channel.join_verified' => '✅ عضویت شما تأیید شد!',
            'channel.join_not_yet' => '❌ هنوز در همه‌ی کانال‌ها عضو نشده‌اید.',

            'blocked.permanent' => '⛔️ دسترسی شما به ربات مسدود شده است.',
            'blocked.temporary' => '⏳ به دلیل فعالیت بیش از حد، دسترسی شما موقتاً تا {until} محدود شده است.',
            'antispam.warning' => '⚠️ لطفاً آرام‌تر! درخواست‌های شما بیش از حد مجاز است.',
            'bot.disabled' => '🤖 ربات در حال حاضر غیرفعال است. لطفاً بعداً مراجعه کنید.',
            'bot.maintenance' => '🛠 ربات در حال حاضر در دست تعمیر است. لطفاً کمی بعد دوباره تلاش کنید.',
            'common.access_denied' => '⛔️ این بخش مخصوص مدیر است.',
        ];
    }

    /** @return array<string, string> key => button label */
    public static function buttons(): array
    {
        return [
            'menu.get_config' => '🎁 دریافت کانفیگ',
            'menu.tutorials' => '📚 آموزش‌ها',
            'menu.referral' => '👥 زیرمجموعه‌گیری',
            'menu.profile' => '👤 پروفایل',
            'menu.coin_store' => '🪙 دریافت کانفیگ با سکه',
            'menu.my_configs' => '🔑 اشتراک‌های من',
            'menu.admin' => '⚙️ پنل مدیریت',

            'config.new' => '🆕 دریافت کانفیگ جدید',
            'config.renew' => '♻️ تمدید کانفیگ فعلی',
            'config.status' => '🔑 اشتراک‌های من',
            'config.rotate' => '🔄 تعویض لینک اشتراک',
            'config.single_configs' => '📥 دریافت کانفیگ‌ها به‌صورت تکی',

            'profile.history' => '🗂 تاریخچه',

            'channel.joined' => '✅ عضو شدم',
            'referral.share' => '📤 ارسال لینک دعوت',
            'tutorials.back' => '🔙 بازگشت به آموزش‌ها',

            'common.back' => '🔙 بازگشت',
            'common.back_menu' => '🔙 بازگشت به منو',
        ];
    }
}
