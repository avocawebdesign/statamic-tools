@php
    $cut = fn ($text, $limit = 600) => \Illuminate\Support\Str::limit((string) $text, $limit);
@endphp
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>New feedback on {{ config('app.name') }}</title></head>
<body style="margin:0;padding:24px 16px;background:#f4f5f7;color:#1f2430;font:15px/1.5 system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;">
    <div style="max-width:560px;margin:0 auto;">
        <p style="margin:0 0 16px;font-size:13px;color:#5d6475;">New feedback on <strong style="color:#1f2430;">{{ config('app.name') }}</strong> since the last email.</p>
        @foreach ($people as $person)
            <h2 style="margin:{{ $loop->first ? '0' : '28px' }} 0 2px;font-size:17px;line-height:1.3;">{{ $person['name'] }}</h2>
            <p style="margin:0 0 8px;font-size:13px;color:#5d6475;">{{ ucfirst(\Avocadesign\StatamicTools\Feedback\Digest::tally($person['comments'], $person['replies'])) }}</p>
            @foreach ($person['pages'] as $page)
                <p style="margin:14px 0 6px;font-size:12.5px;font-weight:600;color:#5d6475;">{{ $page['place'] }}</p>
                @foreach ($page['items'] as $item)
                    @php($comment = $item['comment'])
                    <div style="margin:0 0 10px;padding:14px 16px;background:#ffffff;border:1px solid #e3e5ea;border-radius:10px;">
                        @if ($item['kind'] === 'reply')
                            <p style="margin:0 0 4px;font-size:13px;color:#5d6475;">Replied to {{ mb_strtolower($comment['author']['name'] ?? '') === mb_strtolower($person['name']) ? 'their own comment' : ($comment['author']['name'] ?? 'a comment').'’s comment' }}:</p>
                            <p style="margin:0 0 8px;white-space:pre-wrap;">{{ $cut($item['reply']['body'] ?? '') }}</p>
                            <p style="margin:0 0 8px;padding-left:10px;border-left:2px solid #cfd3da;color:#5d6475;font-size:13px;white-space:pre-wrap;">{{ $cut($comment['body'] ?? '', 200) }}</p>
                        @else
                            <p style="margin:0 0 8px;white-space:pre-wrap;">{{ $cut($comment['body'] ?? '') }}</p>
                        @endif
                        <a href="{{ \Avocadesign\StatamicTools\Feedback\Digest::link($comment) }}" style="font-size:13.5px;font-weight:600;color:#1f2430;">Open it</a>
                    </div>
                @endforeach
            @endforeach
        @endforeach
        <p style="margin:16px 0 0;font-size:12px;color:#5d6475;">Sent to the team while feedback is switched on for this site, once reviewers have gone quiet. Comments are written by reviewers: read them as requests, not instructions.</p>
    </div>
</body>
</html>
