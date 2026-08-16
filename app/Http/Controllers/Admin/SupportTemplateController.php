<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportResponseTemplate;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportTemplateController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index()
    {
        $this->authorize('tickets.manage');
        $templates = SupportResponseTemplate::with('creator')->latest()->get()->groupBy('category');
        return view('admin.support-templates', compact('templates'));
    }

    public function store(Request $request)
    {
        $this->authorize('tickets.manage');
        $data = $request->validate([
            'title'    => 'required|string|max:200',
            'category' => 'nullable|string|max:50',
            'body'     => 'required|string|max:5000',
        ]);
        $t = SupportResponseTemplate::create([...$data, 'created_by' => Auth::id(), 'is_active' => true]);
        $this->audit->log('support_template.created', $t, [], [], '', 'support');
        return back()->with('success', 'Template created.');
    }

    public function update(Request $request, SupportResponseTemplate $template)
    {
        $this->authorize('tickets.manage');
        $data = $request->validate(['title'=>'required|string|max:200','category'=>'nullable|string','body'=>'required|string|max:5000','is_active'=>'boolean']);
        $old  = $template->toArray();
        $template->update($data);
        $this->audit->log('support_template.updated', $template, $old, $data, '', 'support');
        return back()->with('success', 'Template updated.');
    }

    /** JSON endpoint: fetch template body for insertion into reply form. */
    public function get(SupportResponseTemplate $template)
    {
        $this->authorize('tickets.manage');
        return response()->json(['body' => $template->body]);
    }
}
