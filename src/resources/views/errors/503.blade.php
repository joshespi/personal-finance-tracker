@include('errors.minimal', [
    'code'    => 503,
    'heading' => "Back in a moment",
    'message' => "The app is down for maintenance. Try again shortly.",
    'retry'   => true,
])
