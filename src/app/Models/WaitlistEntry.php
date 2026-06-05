<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaitlistEntry extends Model
{
  protected $fillable = ['email', 'handle', 'did', 'status', 'invited_at', 'display_name', 'avatar_url', 'description'];

  protected $casts = [
    'invited_at' => 'datetime',
  ];

  public function inviteCode()
  {
    return $this->hasOne(InviteCode::class);
  }

  public function isPending(): bool
  {
    return $this->status === 'pending';
  }

  public function isInvited(): bool
  {
    return $this->status === 'invited';
  }
}
