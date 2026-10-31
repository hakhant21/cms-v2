<?php

namespace App\Livewire;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

/**
 * The contact form island.
 *
 * The component writes the enquiry to the inbox first and only then queues the
 * notification, so a mail failure can never lose a message. A honeypot field
 * and a submission timestamp keep cheap bots out without a CAPTCHA.
 */
class ContactForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $subject = '';

    public string $message = '';

    /** Filled in by bots only; a real visitor never sees the field. */
    public string $website = '';

    public bool $sent = false;

    /** Unix timestamp of the render, used to reject instant submissions. */
    public int $renderedAt = 0;

    public function mount(): void
    {
        $this->renderedAt = now()->getTimestamp();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['required', 'string', 'min:3', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Please tell us your name.',
            'email.required' => 'We need an email address to reply to.',
            'email.email' => 'That email address does not look right.',
            'subject.required' => 'Please add a short subject.',
            'message.required' => 'Please write your message.',
            'message.min' => 'Your message is a little short — a sentence or two helps.',
        ];
    }

    public function submit(): void
    {
        if (! setting_bool('contact.form_enabled', true)) {
            $this->addError('form', 'The contact form is closed right now.');

            return;
        }

        $this->validate();

        // Bots fill every field and submit instantly; accept silently so they
        // do not learn that they were caught.
        $instant = now()->getTimestamp() - $this->renderedAt < 2;

        if (trim($this->website) !== '' || $instant) {
            $this->reset('name', 'email', 'phone', 'subject', 'message', 'website');
            $this->sent = true;

            return;
        }

        $contactMessage = ContactMessage::create([
            'name' => trim($this->name),
            'email' => trim($this->email),
            'phone' => trim($this->phone) ?: null,
            'subject' => trim($this->subject),
            'message' => trim($this->message),
            'locale' => app()->getLocale(),
            'status' => ContactMessage::STATUS_NEW,
        ]);

        $recipient = setting('contact.notify_email') ?: setting('company.email');

        if (filled($recipient)) {
            Mail::to($recipient)->queue(new ContactMessageReceived($contactMessage));
        }

        $this->reset('name', 'email', 'phone', 'subject', 'message', 'website');
        $this->sent = true;
        $this->renderedAt = now()->getTimestamp();
    }

    public function render()
    {
        return view('livewire.contact-form', [
            'enabled' => setting_bool('contact.form_enabled', true),
            'successMessage' => setting('contact.success_message')
                ?: 'Thank you for your message. Our team will get back to you shortly.',
        ]);
    }
}
