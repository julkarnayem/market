<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerVerification;
use App\Services\AuditLogger;
use App\Services\VerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class VerificationController extends Controller
{
    public function __construct(
        private readonly VerificationService $service,
        private readonly AuditLogger $audit,
    ) {}

    public function index()
    {
        $tab = request('tab','pending');
        $verifications = SellerVerification::where('status',$tab)
            ->with('user','reviewer')->latest()->paginate(20);
        return view('admin.verification', compact('verifications','tab'));
    }

    public function show(SellerVerification $verification)
    {
        $this->authorize('verification.review');
        $verification->load('user','reviewer');
        return view('admin.verification-show', compact('verification'));
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
}
