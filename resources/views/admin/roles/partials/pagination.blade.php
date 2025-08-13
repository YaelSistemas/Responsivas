@if(method_exists($roles, 'links'))
  <div class="mt-4">
    {{ $roles->withQueryString()->links() }}
  </div>
@endif
