<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup automat zilnic
// 01:00 — șterge backup-uri vechi conform politicii din config/backup.php
Schedule::command('backup:clean')
    ->daily()
    ->at('01:00')
    ->withoutOverlapping();

// 02:00 — creează backup nou (rulează după clean, nu în același timp)
Schedule::command('backup:run')
    ->daily()
    ->at('02:00')
    ->withoutOverlapping();

// Sincronizare email-uri Gmail primite — la fiecare 5 minute
Schedule::command('gmail:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Notificare WhatsApp către sales_rep pentru oportunități blocate — rulează
// orar, dar comanda însăși acționează doar la ora din automation_settings
// (opportunity_stuck.send_hour, implicit 09:00) — configurabilă din
// /admin/automation-settings, fără redeploy.
Schedule::command('opportunities:check-stuck')
    ->hourly()
    ->withoutOverlapping();
