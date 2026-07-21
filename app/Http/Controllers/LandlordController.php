<?php

namespace App\Http\Controllers;

use App\Models\LandlordApplication;
use App\Models\Property;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\Services\RequestDetailsPdfExporter;
use App\Traits\CreatesNotifications;

class LandlordController extends Controller
{
    use CreatesNotifications;

    private const SESSION_KEYS = [
        'requests' => 'landlord_seen_requests_at',
        'active' => 'landlord_seen_active_at',
        'published' => 'landlord_seen_published_at',
        'pending' => 'landlord_seen_pending_at',
        'rejected' => 'landlord_seen_rejected_at',
    ];

    public function dashboard()
    {
        app(\App\Services\TransactionWorkflowService::class)->cancelExpiredPendingTransactions();

        $user = Auth::user();
        $propertyIds = Property::where('user_id', $user->id)->pluck('id');

        $seenRequests = Session::get(self::SESSION_KEYS['requests']);
        $seenActive = Session::get(self::SESSION_KEYS['active']);
        $seenPublished = Session::get(self::SESSION_KEYS['published']);
        $seenPending = Session::get(self::SESSION_KEYS['pending']);
        $seenRejected = Session::get(self::SESSION_KEYS['rejected']);

        // Get properties grouped by status
        $approvedProperties = Property::where('user_id', $user->id)
            ->where('status', 'approved')
            ->with(['images' => function($query) {
                $query->orderBy('is_primary', 'desc')->limit(1);
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        $pendingProperties = Property::where('user_id', $user->id)
            ->where('status', 'pending')
            ->with(['images' => function($query) {
                $query->orderBy('is_primary', 'desc')->limit(1);
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        $rejectedProperties = Property::where('user_id', $user->id)
            ->where('status', 'rejected')
            ->with(['images' => function($query) {
                $query->orderBy('is_primary', 'desc')->limit(1);
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        // Get transaction requests for landlord's properties
        $transactionRequests = Transaction::whereIn('property_id', $propertyIds)
            ->with(['user', 'property'])
            ->where('status', 'pending')
            ->where(function($query) {
                $query->whereNull('contract_generated_at');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Get transactions awaiting actions (contract approved, payment pending, etc)
        $activeTransactions = Transaction::whereIn('property_id', $propertyIds)
            ->with(['user', 'property'])
            ->where(function ($q) {
                $q->whereIn('status', ['confirmed'])->orWhere('paid', true);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // New counts: only items since last time landlord opened that section
        $newPendingRequests = $seenRequests
            ? $transactionRequests->where('created_at', '>', $seenRequests)->count()
            : $transactionRequests->count();
        $newActiveTransactions = $seenActive
            ? $activeTransactions->where('created_at', '>', $seenActive)->count()
            : $activeTransactions->count();
        $newPublished = $seenPublished
            ? $approvedProperties->where('created_at', '>', $seenPublished)->count()
            : $approvedProperties->count();
        $newPendingProperties = $seenPending
            ? $pendingProperties->where('created_at', '>', $seenPending)->count()
            : $pendingProperties->count();
        $newRejected = $seenRejected
            ? $rejectedProperties->where('updated_at', '>', $seenRejected)->count()
            : $rejectedProperties->count();

        $stats = [
            'total_properties' => Property::where('user_id', $user->id)->count(),
            'active_listings' => Property::where('user_id', $user->id)->where('status', 'approved')->count(),
            'pending_properties' => $pendingProperties->count(),
            'total_views' => 0,
            'total_likes' => DB::table('property_likes')
                ->join('properties', 'property_likes.property_id', '=', 'properties.id')
                ->where('properties.user_id', $user->id)
                ->count(),
            'new_pending_requests' => $newPendingRequests,
            'new_active_transactions' => $newActiveTransactions,
            'new_published' => $newPublished,
            'new_pending_properties' => $newPendingProperties,
            'new_rejected' => $newRejected,
        ];

        return view('landlord.dashboard', compact('approvedProperties', 'pendingProperties', 'rejectedProperties', 'stats', 'transactionRequests', 'activeTransactions'));
    }

    /**
     * Export request details as a PDF document.
     */
    public function exportRequestPdf(Transaction $transaction, RequestDetailsPdfExporter $exporter)
    {
        $transaction->load(['user', 'property.rules', 'property.amenities']);

        $property = $transaction->property;
        if (!$property || (int) $property->user_id !== (int) Auth::id()) {
            abort(403, 'You do not have access to this request.');
        }

        try {
            $path = $exporter->export($transaction);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('landlord.dashboard')
                ->with('error', 'Could not export the request PDF. Please try again.');
        }

        $filename = 'request-' . $transaction->id . '.pdf';

        return response()->download(
            $path,
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ],
            'attachment'
        )->deleteFileAfterSend(true);
    }

    public function markSectionSeen(Request $request)
    {
        $section = $request->input('section');
        if (!isset(self::SESSION_KEYS[$section])) {
            return response()->json(['success' => false, 'message' => 'Invalid section'], 400);
        }
        Session::put(self::SESSION_KEYS[$section], now()->toDateTimeString());
        return response()->json(['success' => true]);
    }

    /**
     * Send a rejected listing back to the admin review queue after the landlord has addressed it.
     */
    public function resubmitProperty(Request $request, Property $property)
    {
        $user = Auth::user();
        if ((int) $property->user_id !== (int) $user->id) {
            abort(403);
        }
        if ($property->status !== 'rejected') {
            return redirect()->route('landlord.dashboard')
                ->with('error', 'Only rejected listings can be resubmitted for approval.');
        }

        $validator = Validator::make($request->all(), [
            'resubmit_notes' => ['required', 'string', 'min:15', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('landlord.dashboard')
                ->withFragment('rejected-section')
                ->withErrors($validator)
                ->withInput()
                ->with('resubmit_failed_property_id', $property->id);
        }

        $notes = trim($validator->validated()['resubmit_notes']);

        $property->update([
            'status' => 'pending',
            'rejection_reason' => null,
            'resubmit_notes' => $notes,
            'approved_at' => null,
            'approved_by' => null,
        ]);

        User::where('role', 'admin')->each(function (User $admin) use ($property, $user, $notes) {
            $this->createNotification(
                $admin,
                'pending',
                'Property Resubmitted',
                $user->name . ' resubmitted "' . $property->title . '" for approval.' . ($notes ? ' Note: ' . $notes : ''),
                $property
            );
        });

        return redirect()->route('landlord.dashboard')
            ->with('success', 'Your listing has been sent for admin review. You can track it under Pending Approval.');
    }

    public function showApplyForm()
    {
        $user = Auth::user();

        if ($user->role === 'landlord' || $user->role === 'admin') {
            return redirect()->route('landlord.dashboard')
                ->with('info', 'You are already a landlord.');
        }

        $existingApplication = LandlordApplication::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existingApplication) {
            return redirect()->route('landlord.dashboard')
                ->with('info', 'You have a pending landlord application. Please wait for admin approval.');
        }

        return view('landlord.apply');
    }

    public function submitApplication(Request $request)
    {
        $user = Auth::user();

        if ($user->role === 'landlord' || $user->role === 'admin') {
            return redirect()->route('landlord.dashboard')
                ->with('info', 'You are already a landlord.');
        }

        $existingApplication = LandlordApplication::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existingApplication) {
            return redirect()->route('landlord.apply')
                ->with('error', 'You already have a pending application.');
        }

        $validated = $request->validate([
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'document_type' => 'required|in:national_id,trade_license',
            'document_number' => 'required|string|max:255',
            'document_front' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'document_back' => [
                \Illuminate\Validation\Rule::requiredIf(fn () => $request->input('document_type') === 'national_id'),
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:5120',
            ],
            'face_photo' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'notes' => 'nullable|string',
        ]);

        $validated['user_id'] = $user->id;
        $validated['status'] = 'pending';
        if ($validated['document_type'] === 'national_id') {
            $validated['id_number'] = $validated['document_number'];
            $validated['trade_license'] = null;
        } else {
            $validated['trade_license'] = $validated['document_number'];
            $validated['id_number'] = null;
        }
        unset($validated['document_number'], $validated['document_front'], $validated['document_back'], $validated['face_photo']);

        $application = null;
        DB::transaction(function () use (&$application, $validated, $request) {
            $application = LandlordApplication::create($validated);
            $dir = 'landlord-applications/'.$application->id;
            $frontPath = $request->file('document_front')->store($dir, 'public');
            $backPath = $request->hasFile('document_back')
                ? $request->file('document_back')->store($dir, 'public')
                : null;
            $facePath = $request->file('face_photo')->store($dir, 'public');
            $application->update([
                'document_front_path' => $frontPath,
                'document_back_path' => $backPath,
                'face_photo_path' => $facePath,
            ]);
        });
        
        // Create notification for all admins about new pending application
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $this->createNotification(
                $admin,
                'pending',
                'New Landlord Application',
                $user->name . ' has submitted a landlord application. Please review it.',
                $application
            );
        }

        return redirect()->route('landlord.dashboard')
            ->with('success', 'Your landlord application has been submitted successfully! We will review it and get back to you soon.');
    }

    public function properties()
    {
        $user = Auth::user();
        $properties = Property::where('user_id', $user->id)
            ->with(['images', 'amenities', 'rules', 'unit'])
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        return view('landlord.properties', compact('properties'));
    }
}
