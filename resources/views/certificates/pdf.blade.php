<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page{margin:28px}body{font-family:DejaVu Sans,sans-serif;color:#202940;font-size:12px}.frame{border:2px solid {{ $template?->accent ?? '#5653d9' }};padding:30px 48px;height:470px;text-align:center;position:relative;overflow:hidden}.background{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-2}.wash{position:absolute;inset:0;background:rgba(255,255,255,.28);z-index:-1}.inner{position:absolute;top:8px;left:8px;right:8px;bottom:8px;border:1px solid #dbd2b8;z-index:-1}.brand{width:145px;height:auto}.eyebrow{font-size:10px;letter-spacing:4px;color:#958359;margin:20px 0 13px}h1{font-size:32px;font-weight:normal;margin:0;color:#242c45}.name{font-size:31px;margin:22px 0 15px;color:{{ $template?->accent ?? '#5653d9' }}}.course{font-size:20px;font-weight:bold;margin:10px 0}.muted{color:#818597;font-size:11px}.footer{position:absolute;bottom:28px;left:48px;right:48px}.signature{border-top:1px solid #b7a978;padding-top:7px;width:220px;text-align:center}.signature-image{display:block;width:150px;height:55px;object-fit:contain;margin:0 auto 3px}.qr{width:86px;height:86px}.number{font-size:8px;letter-spacing:1px;color:#777e90}
    </style>
</head>
<body>
<div class="frame">
    @if($template?->background_image)
        <img class="background" src="{{ public_path('storage/'.$template->background_image) }}">
        <div class="wash"></div>
    @endif
    <div class="inner"></div>
    <img class="brand" src="{{ public_path('assets/logo/aldef-landscape02.png') }}">
    <div class="eyebrow">ALDEF ACADEMY · RECOGNIZING EXCELLENCE</div>
    <h1>{{ $template?->heading ?? 'Certificate of Completion' }}</h1>
    <p class="muted">This certificate is proudly presented to</p>
    <div class="name">{{ $certificate->student_name }}</div>
    <div class="muted">{{ $template?->message ?? 'For successfully completing the learning journey and required assessments in' }}</div>
    <div class="course">{{ $certificate->course_title }}</div>
    <p class="muted">Completed on {{ $certificate->completed_at->format('d F Y') }}</p>
    <div class="footer"><table style="width:100%"><tr>
        <td style="width:40%;text-align:left;vertical-align:bottom"><div class="signature">@if($template?->signature_image)<img class="signature-image" src="{{ public_path('storage/'.$template->signature_image) }}">@endif{{ $template?->signatory ?? $certificate->instructor_name }}<br><span class="muted">{{ $brandName }}</span></div></td>
        <td style="width:30%;text-align:center;vertical-align:bottom"><span class="muted">Issued {{ $certificate->issued_at->format('d F Y') }}</span><p class="number">{{ $certificate->number }}</p></td>
        <td style="width:30%;text-align:right"><img class="qr" src="data:image/svg+xml;base64,{{ base64_encode($qr) }}"><br><a href="{{ $url }}" class="number">SCAN TO VERIFY AUTHENTICITY</a></td>
    </tr></table></div>
</div>
</body>
</html>
