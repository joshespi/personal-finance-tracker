@include('errors.minimal', [
    'code'    => 500,
    'heading' => "Something went wrong",
    'message' => "An unexpected error occurred. Your data is safe. Try again, and if it keeps happening check the application log.",
    'retry'   => true,
])
