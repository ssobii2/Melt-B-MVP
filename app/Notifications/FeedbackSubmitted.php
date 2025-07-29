<?php

namespace App\Notifications;

use App\Models\Feedback;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeedbackSubmitted extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Feedback $feedback
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $priorityLabels = [
            'low' => 'Low',
            'medium' => 'Medium', 
            'high' => 'High',
            'critical' => 'Critical'
        ];
        
        $typeLabels = [
            'general' => 'General Feedback',
            'bug_report' => 'Bug Report',
            'feature_request' => 'Feature Request'
        ];
        
        $submitterInfo = $this->feedback->user 
            ? "User: {$this->feedback->user->name} ({$this->feedback->user->email})"
            : "Anonymous submission";
            
        if ($this->feedback->contact_email && !$this->feedback->user) {
            $submitterInfo = "Contact: {$this->feedback->contact_email}";
        }
        
        return (new MailMessage)
            ->subject('New Feedback Submitted - ' . $this->feedback->subject)
            ->greeting('New Feedback Received')
            ->line('A new feedback has been submitted on the platform.')
            ->line('**Type:** ' . ($typeLabels[$this->feedback->type] ?? $this->feedback->type))
            ->line('**Subject:** ' . $this->feedback->subject)
            ->line('**Priority:** ' . ($priorityLabels[$this->feedback->priority] ?? $this->feedback->priority))
            ->when($this->feedback->category, function ($message) {
                return $message->line('**Category:** ' . $this->feedback->category);
            })
            ->line('**Submitted by:** ' . $submitterInfo)
            ->line('**Description:**')
            ->line($this->feedback->description)

            ->line('Please review and respond to this feedback as appropriate.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'feedback_id' => $this->feedback->id,
            'type' => $this->feedback->type,
            'subject' => $this->feedback->subject,
            'priority' => $this->feedback->priority,
            'submitted_by' => $this->feedback->user?->name ?? 'Anonymous',
            'submitted_at' => $this->feedback->created_at->toISOString(),
            //
        ];
    }
}
