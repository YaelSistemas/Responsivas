@if ($users instanceof \Illuminate\Contracts\Pagination\Paginator)
  {!! $users->withQueryString()->links() !!}
@endif
