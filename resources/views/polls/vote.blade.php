<x-vue-app-layout>
    <x-slot:title>
      Sondage
    </x-slot>

    <x-slot:scripts>
      @vite(['resources/js/poll-vote.js'])
    </x-slot>

    <div id="poll-vote" data-props='@json([
      "token" => $token,
      "loginUrl" => route("login"),
    ])'></div>
  </x-vue-app-layout>