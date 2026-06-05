<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class InviteCode extends Model
{
  use HasUuids;

  protected $keyType = 'string';
  public $incrementing = false;

  protected $fillable = ['code', 'handle', 'did', 'waitlist_entry_id', 'used_by_user_id', 'used_at', 'expires_at'];

  protected $casts = [
    'used_at' => 'datetime',
    'expires_at' => 'datetime',
  ];

  public function usedBy()
  {
    return $this->belongsTo(User::class, 'used_by_user_id');
  }

  public function waitlistEntry()
  {
    return $this->belongsTo(\App\Models\WaitlistEntry::class);
  }

  public function isUsed(): bool
  {
    return !is_null($this->used_by_user_id);
  }

  public function isExpired(): bool
  {
    return $this->expires_at && now()->greaterThan($this->expires_at);
  }
}
