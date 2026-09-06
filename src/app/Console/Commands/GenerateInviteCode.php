<?php

namespace App\Console\Commands;

use App\Models\InviteCode;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateInviteCode extends Command
{
  protected $signature = 'invite:generate {--count=1 : 発行する件数}';

  protected $description = '招待コードを発行します';

  public function handle(): void
  {
    $count = (int) $this->option('count');

    for ($i = 0; $i < $count; $i++) {
      $code = strtoupper(implode('-', str_split(Str::random(12), 4)));
      InviteCode::create(['code' => $code]);
      $this->line("  <info>✓</info> {$code}");
    }

    $this->newLine();
    $this->info("{$count} 件の招待コードを発行しました。");
  }
}
