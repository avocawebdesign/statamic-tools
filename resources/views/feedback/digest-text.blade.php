{{-- Plain text, so nothing is escaped as it would be for HTML. --}}
New feedback on {!! config('app.name') !!} since the last email.
@foreach ($people as $person)

{!! $person['name'] !!}: {!! \Avocadesign\StatamicTools\Feedback\Digest::tally($person['comments'], $person['replies']) !!}
@foreach ($person['pages'] as $page)

  {!! $page['place'] !!}
@foreach ($page['items'] as $item)
@if ($item['kind'] === 'reply')
  - Replied: {!! \Illuminate\Support\Str::limit((string) ($item['reply']['body'] ?? ''), 600) !!}
@else
  - {!! \Illuminate\Support\Str::limit((string) ($item['comment']['body'] ?? ''), 600) !!}
@endif
    {!! \Avocadesign\StatamicTools\Feedback\Digest::link($item['comment']) !!}
@endforeach
@endforeach
@endforeach

Sent to the team while feedback is switched on for this site, once reviewers have gone quiet.
