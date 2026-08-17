<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerVerification;
use App\Services\AuditLogger;
use App\Services\VerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class VerificationController extends Controller
{
    public function __construct(
        private readonly VerificationService $service,
        private readonly AuditLogger $audit,
    ) {}

    public function index()
    {
        $tab = request('tab', 'pending');

        $verifications = SellerVerification::where('status', $tab)
            ->with('user', 'reviewer')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Verification/Index', [
            'verifications' => $verifications->through(fn (SellerVerification $v) => [
                'id'            => $v->id,
                'user_name'     => $v->user?->name ?? '—',
                'type_label'    => $this->docTypeLabel($v->document_type),
                'submitted'     => $v->created_at->format('d M Y'),
                'status'        => $v->status,
                'reviewer_name' => $v->reviewer?->name ?? '—',
                'is_pending'    => $v->status === 'pending',
                'url'           => route('admin.verification.show', $v),
            ]),
            'tab'  => $tab,
            'tabs' => [
                ['value' => 'pending',  'label' => 'Pending'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'rejected', 'label' => 'Rejected'],
            ],
        ]);
    }

    public function show(SellerVerification $verification)
    {
        $this->authorize('verification.review');
        $verification->load('user', 'reviewer');

        // Document images are strictly the platform owner's (see serveDocument()).
        // has_document still reaches every reviewer so the "restricted" notice can
        // render, but the streamable URLs are serialized for the owner only.
        $isOwner = Auth::user()->hasRole('super_admin');

        return Inertia::render('Admin/Verification/Show', [
            'verification' => [
                'id'            => $verification->id,
                'user_name'     => $verification->user?->name ?? '—',
                'user_email'    => $verification->user?->email ?? '—',
                'type_label'    => $this->docTypeLabel($verification->document_type),
                'attempt'       => $verification->attempt_number,
                'submitted'     => $verification->submitted_at?->format('d M Y, H:i') ?? '—',
                'status'        => $verification->status,
                // Shown only to reviewers, which reaching this page already implies.
                'date_of_birth' => $verification->date_of_birth?->format('d M Y'),
                'reviewer_name' => $verification->reviewer?->name ?? 'admin',
                'is_pending'    => $verification->status === 'pending',
                'has_document'      => (bool) $verification->document_path,
                'has_document_back' => (bool) $verification->document_back_path,
                // Route strings only, and only for the owner — serveDocument
                // re-checks super_admin before streaming regardless.
                'document_url'      => ($isOwner && $verification->document_path)
                    ? route('admin.verification.document', [$verification, 'document'])
                    : null,
                'document_back_url' => ($isOwner && $verification->document_back_path)
                    ? route('admin.verification.document', [$verification, 'document_back'])
                    : null,
                // The encrypted nid_number is intentionally never sent to the client.
            ],
            'canViewDocuments' => $isOwner,
        ]);
    }

    public function approve(Request $request, SellerVerification $verification)
    {
        $this->authorize('verification.review');
        $data = $request->validate(['notes'=>'nullable|string|max:500']);
        $old  = ['status'=>$verification->status];
        $this->service->approve($verification, Auth::user(), $data['notes'] ?? null);
        $this->audit->log('verification.approved', $verification, $old, ['status'=>'approved']);
        return redirect()->route('admin.verification.show',$verification)->with('success','Verification approved.');
    }

    public function reject(Request $request, SellerVerification $verification)
    {
        $this->authorize('verification.review');
        $data = $request->validate(['reason'=>'required|string|max:500','notes'=>'nullable|string|max:500']);
        $old  = ['status'=>$verification->status];
        $this->service->reject($verification, Auth::user(), $data['reason'], $data['notes'] ?? null);
        $this->audit->log('verification.rejected', $verification, $old, ['status'=>'rejected']);
        return redirect()->route('admin.verification.show',$verification)->with('success','Verification rejected.');
    }

    /**
     * Serve verification documents — SUPER ADMIN ONLY.
     * No staff, manager, support or moderator can access these images.
     * Only the platform owner (super_admin role) can view them.
     */
    public function serveDocument(SellerVerification $verification, string $type)
    {
        // STRICT: only super_admin role — not admin, not moderator, not support, not finance
        abort_unless(
            auth()->user()->hasRole('super_admin'),
            403,
            'Access denied. Only the platform owner can view verification documents.'
        );

        abort_unless(in_array($type, ['document', 'document_back']), 404);

        $path = match($type) {
            'document'      => $verification->document_path,
            'document_back' => $verification->document_back_path,
        };

        abort_unless($path && Storage::disk('private')->exists($path), 404);

        $mime = Storage::disk('private')->mimeType($path);
        return response()->stream(
            fn() => fpassthru(Storage::disk('private')->readStream($path)),
            200,
            ['Content-Type' => $mime, 'Content-Disposition' => 'inline']
        );
    }

    /** Human label for the stored document_type code. */
    private function docTypeLabel(string $type): string
    {
        return match ($type) {
            'nid'             => 'National ID (NID)',
            'passport'        => 'Passport',
            'dob'             => 'Date of Birth',
            'driving_license' => 'Driving License',
            default           => strtoupper($type),
        };
    }
}
