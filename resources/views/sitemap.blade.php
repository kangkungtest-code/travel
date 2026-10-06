{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach ($halaman as $h)
    <url>
        <loc>{{ $h['loc'] }}</loc>
@if (! empty($h['lastmod']))
        <lastmod>{{ $h['lastmod']->toAtomString() }}</lastmod>
@endif
@foreach ($h['gambar'] ?? [] as $g)
        <image:image><image:loc>{{ $g }}</image:loc></image:image>
@endforeach
    </url>
@endforeach
</urlset>
