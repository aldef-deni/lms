<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Certificate;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_unless($user->canAny(['certificates.view-own', 'certificates.manage', 'certificates.view-organization']), 403);
        $certificates = Certificate::with('enrollment.user')
            ->when($user->isCorporateAdmin(), fn ($q) => $q->whereHas('enrollment.user', fn ($q) => $q->where('organization_id', $user->organization_id)))
            ->when(! $user->isAdmin() && ! $user->isCorporateAdmin(), fn ($q) => $q->whereHas('enrollment', fn ($q) => $q->where('user_id', $user->id)))
            ->latest()->paginate(15);

        return view('certificates.index', compact('certificates'));
    }

    public function verify(Request $r, ?string $token = null)
    {
        $lookup = $token ?? $r->query('code');
        $certificate = $lookup ? Certificate::where('verification_token', $lookup)->orWhere('number', $lookup)->first() : null;

        return view('certificates.verify', compact('certificate', 'lookup'));
    }

    public function download(Certificate $certificate)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $certificate->enrollment->user_id === $user->id || ($user->isCorporateAdmin() && $certificate->enrollment->user->organization_id === $user->organization_id), 403);
        abort_if($certificate->revoked_at, 403, 'This certificate has been revoked.');
        $url = route('verify', ['token' => $certificate->verification_token]);
        $qr = (new Writer(new ImageRenderer(new RendererStyle(150), new SvgImageBackEnd)))->writeString($url);
        $template = $certificate->enrollment->course->template;

        return Pdf::loadView('certificates.pdf', compact('certificate', 'qr', 'url', 'template'))->setPaper('a4', 'landscape')->download($certificate->number.'.pdf');
    }

    public function revoke(Certificate $certificate)
    {
        abort_unless(auth()->user()->can('certificates.manage'), 403);
        $certificate->update(['revoked_at' => now()]);
        ActivityLog::create(['user_id' => auth()->id(), 'action' => 'Revoked certificate', 'subject' => $certificate->number]);

        return back()->with('success', 'Certificate revoked.');
    }
}
