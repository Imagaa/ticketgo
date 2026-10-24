<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrganizerOrdersExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $orders;
    protected $customFieldKeys = [];

    public function __construct($organizerId, $eventId = null)
    {
        // 1. Ambil data dari database langsung saat class dipanggil
        $query = Order::with(['event', 'user'])
            ->whereHas('event', function($q) use ($organizerId) {
                $q->where('organizer_id', $organizerId);
            })
            ->orderBy('created_at', 'desc');

        if ($eventId) {
            $query->where('event_id', $eventId);
        }

        $this->orders = $query->get();

        // 2. Pindai semua transaksi untuk mencari pertanyaan form kustom apa saja yang ada
        $keys = [];
        foreach ($this->orders as $order) {
            $responses = $order->custom_fields_responses ?? [];
            if (is_array($responses)) {
                foreach (array_keys($responses) as $key) {
                    $keys[$key] = true;
                }
            }
        }
        $this->customFieldKeys = array_keys($keys); // Cth: ['Nomor Identitas', 'Kota Domisili']
    }

    public function collection()
    {
        return $this->orders;
    }

    public function headings(): array
    {
        // Header Kolom Standar
        $headers = [
            'Order ID',
            'Nama Event',
            'Waktu Transaksi',
            'Status',
            'Nama Pembeli',
            'Email Pembeli',
            'Total Tiket',
            'Total Pembayaran (Rp)',
            'Detail Tiket'
        ];

        // Tambahkan Header Kolom Dinamis sesuai pertanyaan form
        foreach ($this->customFieldKeys as $key) {
            $headers[] = $key;
        }

        return $headers;
    }

    public function map($order): array
    {
        // Format Detail Tiket ke dalam 1 baris string rapi
        $tickets = $order->ticket_details ?? [];
        $ticketsStr = '';
        if (is_array($tickets)) {
            foreach($tickets as $t) {
                $qty = $t['quantity'] ?? 0;
                $name = $t['tier_name'] ?? 'Tiket';
                $ticketsStr .= "$qty x $name | ";
            }
        }
        $ticketsStr = rtrim($ticketsStr, ' | ');

        // Susun baris (Row) standar
        $row = [
            $order->order_code,
            $order->event->title ?? '-',
            $order->created_at->format('Y-m-d H:i:s'),
            strtoupper($order->status),
            $order->user->name ?? '-',
            $order->user->email ?? '-',
            $order->quantity,
            $order->total,
            $ticketsStr
        ];

        // Masukkan jawaban form kustom ke kolomnya masing-masing
        $responses = $order->custom_fields_responses ?? [];
        foreach ($this->customFieldKeys as $key) {
            // Jika transaksi ini punya jawaban untuk kolom tersebut, tampilkan. Jika tidak, strip (-)
            $row[] = (is_array($responses) && array_key_exists($key, $responses)) ? $responses[$key] : '-';
        }

        return $row;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]], // Jadikan Baris 1 (Header) tebal
        ];
    }
}