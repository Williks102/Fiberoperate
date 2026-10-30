<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Un projet change de phase ou attend une décision : message court et lien vers sa fiche. */
class ProjectPhaseChanged extends Notification
{
    public function __construct(private readonly string $projectId, private readonly string $title, private readonly string $message) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{title: string, message: string, link: string} */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'link' => "/projets/{$this->projectId}"];
    }
}
