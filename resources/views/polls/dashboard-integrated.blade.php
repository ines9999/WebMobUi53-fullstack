<x-default-layout>
  <x-slot:scripts>
    @vite(['resources/js/poll-dashboard-integrated.js'])
  </x-slot>

  <x-slot:title>
    Mes sondages
  </x-slot>

  <div id="app" data-props='@json(["loginUrl" => route("login")])'></div>
</x-default-layout>
