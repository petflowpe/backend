<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PurchaseOrder $order)
    {
    }

    public function envelope(): Envelope
    {
        $number = $this->order->order_number ?: ('#' . $this->order->id);

        return new Envelope(
            subject: 'Orden de compra ' . $number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.purchase-order',
            with: [
                'order' => $this->order,
                'company' => $this->order->company,
                'supplier' => $this->order->supplier,
            ],
        );
    }
}
