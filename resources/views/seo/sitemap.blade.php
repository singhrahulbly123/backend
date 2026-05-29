<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach($articles as $article)
  <url>
    <loc>{{ config('app.frontend_url', url('/')) }}/news/{{ $article->slug }}</loc>
    <lastmod>{{ $article->updated_at->toAtomString() }}</lastmod>
    <changefreq>hourly</changefreq>
    <priority>0.9</priority>
  </url>
@endforeach
@foreach($categories as $category)
  <url>
    <loc>{{ config('app.frontend_url', url('/')) }}/category/{{ $category->slug }}</loc>
    <lastmod>{{ $category->updated_at->toAtomString() }}</lastmod>
    <changefreq>daily</changefreq>
    <priority>0.7</priority>
  </url>
@endforeach
@foreach($stories as $story)
  <url>
    <loc>{{ config('app.frontend_url', url('/')) }}/web-stories/{{ $story->slug }}</loc>
    <lastmod>{{ $story->updated_at->toAtomString() }}</lastmod>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
  </url>
@endforeach
</urlset>
