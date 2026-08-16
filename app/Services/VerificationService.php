<?php
namespace App\Services;

use App\Contracts\SmsServiceInterface;
use App\Models\SellerVerification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VerificationService
{
    public function __construct(
        private readonly TelegramService    $telegram,
        private readonly SmsServiceInterface $sms,
    ) {}

    public function submit(User $user, array $data, ?UploadedFile $documentFront, ?UploadedFile $documentBack): SellerVerification
    {
        return DB::transaction(function () use ($user, $data, $documentFront, $documentBack) {
            $attempt = $user->verifications()->count() + 1;
            $prefix  = "verifications/user-{$user->id}/attempt-{$attempt}";

            $frontPath = $documentFront ? $this->storePrivate($documentFront, $prefix, 'front') : null;
            $backPath  = $documentBack  ? $this->storePrivate($documentBack,  $prefix, 'back')  : null;

            $docType = match($data['verification_method']) {
                'nid'             => 'NID',
                'passport'        => 'Passport',
                'dob'             => 'Date of Birth',
                'driving_license' => 'Driving License',
                default           => strtoupper($data['verification_method'])
            };

            $verification = $user->verifications()->create([
                'document_type'      => $data['verification_method'],
                'nid_number'         => null,
                'date_of_birth'      => $data['date_of_birth'] ?? null,
                'selfie_path'        => null,
                'document_path'      => $frontPath,
                'document_back_path' => $backPath,
                'status'             => 'pending',
                'submitted_at'       => now(),
                'attempt_number'     => $attempt,
            ]);

            $user->update(['verification_status' => 'pending']);

            // Telegram notification to admin
            $this->telegram->send(
                "🆔 <b>New Verification Submitted</b>\n" .
                "User: {$user->name} (#{$user->id})\n" .
                "Username: @{$user->username}\n" .
                "Document: {$docType}\n" .
                "Attempt: #{$attempt}\n" .
                "Review: " . config('app.url') . "/admin/verification/{$verification->id}"
            );

            return $verification;
        });
    }

    public function approve(SellerVerification $v, User $reviewer, ?string $notes = null): void
    {
        DB::transaction(function () use ($v, $reviewer, $notes) {
            $v->update([
                'status'      => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'admin_notes' => $notes,
            ]);
            $v->user->update(['verification_status' => 'approved']);
        });

        // SMS to user
        if ($v->user->phone) {
            try {
                $this->sms->send($v->user->phone,
                    config('app.name').': Your seller verification has been APPROVED! You can now create listings. Login to get started.'
                );
            } catch (\Throwable $e) {}
        }

        // Telegram
        $this->telegram->send(
            "✅ <b>Verification Approved</b>\n" .
            "User: {$v->user->name} (#{$v->user->id})\n" .
            "Reviewed by: {$reviewer->name}"
        );
    }

    public function reject(SellerVerification $v, User $reviewer, string $reason, ?string $notes = null): void
    {
        DB::transaction(function () use ($v, $reviewer, $reason, $notes) {
            $v->update([
                'status'           => 'rejected',
                'rejection_reason' => $reason,
                'reviewed_by'      => $reviewer->id,
                'reviewed_at'      => now(),
                'admin_notes'      => $notes,
            ]);
            $v->user->update(['verification_status' => 'rejected']);
        });

        // SMS to user with reason
        if ($v->user->phone) {
            try {
                $this->sms->send($v->user->phone,
                    config('app.name').": Your verification was rejected. Reason: {$reason}. Please resubmit with correct documents."
                );
            } catch (\Throwable $e) {}
        }

        // Telegram
        $this->telegram->send(
            "❌ <b>Verification Rejected</b>\n" .
            "User: {$v->user->name} (#{$v->user->id})\n" .
            "Reason: {$reason}\n" .
            "Reviewed by: {$reviewer->name}"
        );
    }

    private function storePrivate(UploadedFile $file, string $prefix, string $name): string
    {
        $ext  = $file->getClientOriginalExtension();
        $path = "{$prefix}/{$name}.{$ext}";
        Storage::disk('private')->put($path, file_get_contents($file->getRealPath()));
        return $path;
    }
}
