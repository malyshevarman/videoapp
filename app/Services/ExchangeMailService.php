<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use jamesiarmes\PhpEws\ArrayType\ArrayOfRecipientsType;
use jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfAllItemsType;
use jamesiarmes\PhpEws\Client;
use jamesiarmes\PhpEws\Enumeration\BodyTypeType;
use jamesiarmes\PhpEws\Enumeration\MessageDispositionType;
use jamesiarmes\PhpEws\Enumeration\ResponseClassType;
use jamesiarmes\PhpEws\Request\CreateItemType;
use jamesiarmes\PhpEws\Type\BodyType;
use jamesiarmes\PhpEws\Type\EmailAddressType;
use jamesiarmes\PhpEws\Type\FileAttachmentType;
use jamesiarmes\PhpEws\Type\MessageType;
use jamesiarmes\PhpEws\Type\SingleRecipientType;

class ExchangeMailService
{
    public function sendHtmlMessage(array $recipients, string $subject, string $htmlBody, ?string $attachmentPath = null): void
    {
        $config = config('services.exchange', []);

        $server = $this->normalizeServer((string) ($config['url'] ?? ''));
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $fromAddress = (string) ($config['email'] ?? '');
        $version = (string) ($config['version'] ?? Client::VERSION_2016);

        if ($server === '' || $username === '' || $password === '' || $fromAddress === '') {
            throw new InvalidArgumentException('Exchange configuration is incomplete.');
        }

        $cleanRecipients = array_values(array_filter(array_map('trim', $recipients)));

        if ($cleanRecipients === []) {
            throw new InvalidArgumentException('No email recipients were provided.');
        }

        $client = new Client($server, $username, $password, $version);

        $request = new CreateItemType();
        $request->MessageDisposition = MessageDispositionType::SEND_AND_SAVE_COPY;
        $request->Items = new NonEmptyArrayOfAllItemsType();

        $message = new MessageType();
        $message->Subject = $subject;

        $message->ToRecipients = new ArrayOfRecipientsType();
        foreach ($cleanRecipients as $recipient) {
            $mailbox = new EmailAddressType();
            $mailbox->EmailAddress = $recipient;
            $message->ToRecipients->Mailbox[] = $mailbox;
        }

        $fromMailbox = new EmailAddressType();
        $fromMailbox->EmailAddress = $fromAddress;
        $message->From = new SingleRecipientType();
        $message->From->Mailbox = $fromMailbox;

        $message->Body = new BodyType();
        $message->Body->BodyType = BodyTypeType::HTML;
        $message->Body->_ = $htmlBody;

        if ($attachmentPath !== null && is_file($attachmentPath)) {
            $attachment = new FileAttachmentType();
            $attachment->Name = basename($attachmentPath);
            $attachment->Content = file_get_contents($attachmentPath);

            if ($attachment->Content === false) {
                throw new RuntimeException('Failed to read the attachment for Exchange email.');
            }

            $message->Attachments[] = $attachment;
        }

        $request->Items->Message[] = $message;

        $response = $client->CreateItem($request);
        $messages = $response->ResponseMessages->CreateItemResponseMessage ?? [];

        foreach ($messages as $ewsMessage) {
            if (($ewsMessage->ResponseClass ?? null) === ResponseClassType::SUCCESS) {
                return;
            }

            $code = $ewsMessage->ResponseCode ?? 'UnknownResponseCode';
            $text = $ewsMessage->MessageText ?? 'Exchange did not provide an error message.';

            throw new RuntimeException(sprintf('Exchange send failed: %s (%s)', $text, $code));
        }

        throw new RuntimeException('Exchange did not return a send result.');
    }

    private function normalizeServer(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            throw new InvalidArgumentException('Exchange URL is invalid.');
        }

        return $host;
    }
}
