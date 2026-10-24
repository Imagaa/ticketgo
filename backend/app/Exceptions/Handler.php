use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Register the exception handling callbacks for the application.
 */
public function register(): void
{
    $this->renderable(function (TooManyRequestsHttpException $e, Request $request) {
        if ($request->is('api/*')) {
            return response()->json([
                'message' => 'Too many requests. Please slow down.',
                'retry_after' => $e->getHeaders()['Retry-After'] ?? 60,
            ], 429);
        }
    });
}
