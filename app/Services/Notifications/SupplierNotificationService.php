<?php

namespace App\Services\Notifications;

use App\Models\SupplierOrder;
use App\Models\SupplierStore;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SupplierNotificationService
{
    public function supplierRegistrationSubmitted(SupplierStore $store): bool
    {
        return $this->send(
            $store->notificationEmail,
            'Pengajuan supplier SmartFarm diterima',
            "Halo {$store->nama},\n\nPengajuan toko supplier Anda berhasil diterima sistem dan sedang menunggu verifikasi super admin.\n\nKami akan mengirimkan email lagi setelah pengajuan disetujui atau ditolak.\n\nTerima kasih."
        );
    }

    public function supplierApproved(SupplierStore $store): bool
    {
        $note = trim((string) $store->approvalReason);
        $message = "Halo {$store->nama},\n\nPengajuan toko supplier Anda telah disetujui. Toko sudah dapat tampil untuk owner SmartFarm.";

        if ($note !== '') {
            $message .= "\n\nCatatan admin: {$note}";
        }

        return $this->send($store->notificationEmail, 'Pengajuan supplier disetujui', $message."\n\nTerima kasih.");
    }

    public function supplierRejected(SupplierStore $store, string $reason): bool
    {
        return $this->send(
            $store->notificationEmail,
            'Pengajuan supplier belum disetujui',
            "Halo {$store->nama},\n\nPengajuan toko supplier Anda belum dapat disetujui.\n\nAlasan: {$reason}\n\nSilakan perbaiki data sesuai arahan admin, lalu hubungi pengelola SmartFarm bila perlu."
        );
    }

    public function orderRejected(SupplierOrder $order, string $reason): bool
    {
        $order->loadMissing(['customer', 'store']);

        return $this->send(
            $order->customer?->email,
            'Pesanan supplier ditolak',
            "Halo {$order->customer?->name},\n\nPesanan #".strtoupper(substr($order->id, 0, 12))." ditolak oleh supplier {$order->store?->nama}.\n\nAlasan: {$reason}\n\nSilakan pilih produk atau supplier lain dari katalog SmartFarm."
        );
    }

    private function send(?string $email, string $subject, string $message): bool
    {
        $email = trim((string) $email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::info('[Supplier Notification] Email penerima tidak tersedia.', [
                'subject' => $subject,
            ]);

            return false;
        }

        try {
            Mail::raw($message, function ($mail) use ($email, $subject) {
                $mail->to($email)->subject($subject);
            });

            return true;
        } catch (\Throwable $exception) {
            Log::warning('[Supplier Notification] Gagal mengirim email, fallback ke log.', [
                'email' => $email,
                'subject' => $subject,
                'error' => $exception->getMessage(),
            ]);
            Log::info('[Supplier Notification] Payload', [
                'email' => $email,
                'subject' => $subject,
                'message' => $message,
            ]);

            return false;
        }
    }
}
