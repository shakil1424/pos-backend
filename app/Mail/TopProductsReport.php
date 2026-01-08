<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TopProductsReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $topProducts,
        public $startDate,
        public $endDate,
        public $tenantName
    ) {}

    public function build()
    {
        return $this->subject("Top Products Report: {$this->startDate} to {$this->endDate}")
            ->view('emails.reports.top-products'); // Use view() instead of markdown()
    }
}