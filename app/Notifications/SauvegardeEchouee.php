<?php

namespace App\Notifications;

use App\Models\Sauvegarde;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerte envoyée aux administrateurs quand une sauvegarde échoue (Algorithme §16).
 */
class SauvegardeEchouee extends Notification
{
    public function __construct(public readonly Sauvegarde $sauvegarde) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Échec de la sauvegarde de la base')
            ->line('La sauvegarde du '.$this->sauvegarde->created_at->format('d/m/Y à H:i').' a échoué.')
            ->line($this->sauvegarde->message)
            ->line('Le détail technique est dans le journal de l\'application (storage/logs/laravel.log).')
            ->action('Voir les sauvegardes', route('sauvegardes.index'));
    }
}
