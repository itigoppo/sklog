<?php

namespace App\Mail;

use App\Models\InviteCode;
use App\Models\WaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InviteCodeMail extends Mailable
{
  use Queueable, SerializesModels;

  public function __construct(
    public readonly WaitlistEntry $entry,
    public readonly InviteCode $inviteCode,
  ) {}

  public function envelope(): Envelope
  {
    return new Envelope(
      subject: 'SkLoGへご招待します',
    );
  }

  public function content(): Content
  {
    return new Content(
      view: 'emails.invite_code',
    );
  }
}
