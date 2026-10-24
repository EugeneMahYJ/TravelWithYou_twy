{{ $replyBody }}

> {{ str_replace("\n", "\n> ", $originalMessage) }}

{{ config('app.name', 'Travel With You') }}
@if(!empty($company['phone'])){{ $company['phone'] }}@endif
@if(!empty($company['email'])){{ $company['email'] }}@endif
@if(!empty($company['hours'])){{ $company['hours'] }}@endif
