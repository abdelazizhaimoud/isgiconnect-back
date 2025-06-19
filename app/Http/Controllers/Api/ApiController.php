<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ApiController extends Controller
{
    /**
     * Return success response.
     *
     * @param  mixed  $data
     * @param  string  $message
     * @param  int  $statusCode
     * @return JsonResponse
     */
    protected function successResponse($data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Return error response.
     *
     * @param  string  $message
     * @param  int  $statusCode
     * @param  array|null  $errors
     * @return JsonResponse
     */
    protected function errorResponse(string $message = 'Error', int $statusCode = 400, ?array $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return paginated response.
     *
     * @param  mixed  $data
     * @param  string  $message
     * @param  array  $meta
     * @return JsonResponse
     */
    protected function paginatedResponse($data, string $message = 'Success', array $meta = []): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
                'has_more_pages' => $data->hasMorePages(),
                'links' => [
                    'first' => $data->url(1),
                    'last' => $data->url($data->lastPage()),
                    'prev' => $data->previousPageUrl(),
                    'next' => $data->nextPageUrl(),
                ],
            ],
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response);
    }

    /**
     * Return validation error response.
     *
     * @param  array  $errors
     * @param  string  $message
     * @return JsonResponse
     */
    protected function validationErrorResponse(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->errorResponse($message, 422, $errors);
    }

    /**
     * Return unauthorized response.
     *
     * @param  string  $message
     * @return JsonResponse
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Return forbidden response.
     *
     * @param  string  $message
     * @return JsonResponse
     */
    protected function forbiddenResponse(string $message = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Return not found response.
     *
     * @param  string  $message
     * @return JsonResponse
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Return internal server error response.
     *
     * @param  string  $message
     * @return JsonResponse
     */
    protected function serverErrorResponse(string $message = 'Internal server error'): JsonResponse
    {
        return $this->errorResponse($message, 500);
    }

    /**
     * Get the authenticated user ID.
     *
     * @return int|null
     */
    protected function getCurrentUserId(): ?int
    {
        return auth()->id();
    }

    /**
     * Get the authenticated user ID or fail.
     *
     * @return int
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function requireCurrentUserId(): int
    {
        $userId = auth()->id();
        
        if (!$userId) {
            abort(401, 'Authentication required');
        }
        
        return $userId;
    }

    /**
     * Get the authenticated user.
     *
     * @return \App\Models\User\User|null
     */
    protected function getCurrentUser()
    {
        return auth()->user();
    }

    /**
     * Get the authenticated user or fail.
     *
     * @return \App\Models\User\User
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function requireCurrentUser()
    {
        $user = auth()->user();
        
        if (!$user) {
            abort(401, 'Authentication required');
        }
        
        return $user;
    }

    /**
     * Check if current user has permission.
     *
     * @param  string  $permission
     * @return bool
     */
    protected function userCan(string $permission): bool
    {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            return false;
        }
        
        return $user->hasPermission($permission);
    }

    /**
     * Check if current user has role.
     *
     * @param  string  $role
     * @return bool
     */
    protected function userHasRole(string $role): bool
    {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            return false;
        }
        
        return $user->hasRole($role);
    }

    /**
     * Require permission or abort.
     *
     * @param  string  $permission
     * @param  string  $message
     * @return void
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function requirePermission(string $permission, string $message = 'Insufficient permissions'): void
    {
        if (!$this->userCan($permission)) {
            abort(403, $message);
        }
    }

    /**
     * Require role or abort.
     *
     * @param  string  $role
     * @param  string  $message
     * @return void
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function requireRole(string $role, string $message = 'Insufficient permissions'): void
    {
        if (!$this->userHasRole($role)) {
            abort(403, $message);
        }
    }

    /**
     * Format file size to human readable format.
     *
     * @param  int  $bytes
     * @param  int  $precision
     * @return string
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Sanitize input for search.
     *
     * @param  string  $input
     * @return string
     */
    protected function sanitizeSearchInput(string $input): string
    {
// Remove special characters that might interfere with search
       $sanitized = preg_replace('/[^\w\s\-\.@]/', '', $input);
       
       // Trim whitespace
       return trim($sanitized);
   }

   /**
    * Build metadata response for collections.
    *
    * @param  array  $data
    * @return array
    */
   protected function buildMetadata(array $data = []): array
   {
       $defaultMeta = [
           'timestamp' => now()->toISOString(),
           'version' => '1.0',
           'server' => config('app.name'),
       ];

       return array_merge($defaultMeta, $data);
   }

   /**
    * Handle file upload response.
    *
    * @param  mixed  $uploadResult
    * @param  string  $successMessage
    * @param  string  $errorMessage
    * @return JsonResponse
    */
   protected function handleUploadResponse($uploadResult, string $successMessage = 'File uploaded successfully', string $errorMessage = 'Upload failed'): JsonResponse
   {
       if ($uploadResult) {
           return $this->successResponse($uploadResult, $successMessage, 201);
       }

       return $this->errorResponse($errorMessage, 500);
   }

   /**
    * Transform collection for API response.
    *
    * @param  \Illuminate\Support\Collection  $collection
    * @param  callable  $transformer
    * @return array
    */
   protected function transformCollection($collection, callable $transformer): array
   {
       return $collection->map($transformer)->toArray();
   }

   /**
    * Check if request wants JSON response.
    *
    * @return bool
    */
   protected function wantsJson(): bool
   {
       return request()->wantsJson() || request()->expectsJson();
   }

   /**
    * Get client IP address.
    *
    * @return string
    */
   protected function getClientIp(): string
   {
       return request()->ip();
   }

   /**
    * Get user agent.
    *
    * @return string|null
    */
   protected function getUserAgent(): ?string
   {
       return request()->userAgent();
   }

   /**
    * Log API activity.
    *
    * @param  string  $action
    * @param  mixed  $subject
    * @param  array  $properties
    * @return void
    */
   protected function logActivity(string $action, $subject = null, array $properties = []): void
   {
       if (class_exists('\App\Models\System\Activity')) {
           \App\Models\System\Activity::log($action, $subject, array_merge($properties, [
               'ip_address' => $this->getClientIp(),
               'user_agent' => $this->getUserAgent(),
               'endpoint' => request()->fullUrl(),
               'method' => request()->method(),
           ]));
       }
   }

   /**
    * Rate limit check helper.
    *
    * @param  string  $key
    * @param  int  $maxAttempts
    * @param  int  $decayMinutes
    * @return bool
    */
   protected function isRateLimited(string $key, int $maxAttempts = 60, int $decayMinutes = 1): bool
   {
       $key = 'rate_limit:' . $key . ':' . $this->getClientIp();
       
       if (cache()->has($key)) {
           $attempts = cache()->get($key, 0);
           
           if ($attempts >= $maxAttempts) {
               return true;
           }
           
           cache()->put($key, $attempts + 1, now()->addMinutes($decayMinutes));
       } else {
           cache()->put($key, 1, now()->addMinutes($decayMinutes));
       }
       
       return false;
   }

   /**
    * Apply common filters to query.
    *
    * @param  \Illuminate\Database\Eloquent\Builder  $query
    * @param  \Illuminate\Http\Request  $request
    * @param  array  $allowedFilters
    * @return \Illuminate\Database\Eloquent\Builder
    */
   protected function applyFilters($query, $request, array $allowedFilters = []): \Illuminate\Database\Eloquent\Builder
   {
       foreach ($allowedFilters as $filter => $column) {
           if ($request->has($filter)) {
               $value = $request->get($filter);
               
               if (is_array($value)) {
                   $query->whereIn($column, $value);
               } else {
                   $query->where($column, $value);
               }
           }
       }
       
       return $query;
   }

   /**
    * Apply common sorting to query.
    *
    * @param  \Illuminate\Database\Eloquent\Builder  $query
    * @param  \Illuminate\Http\Request  $request
    * @param  array  $allowedSorts
    * @param  string  $defaultSort
    * @param  string  $defaultDirection
    * @return \Illuminate\Database\Eloquent\Builder
    */
   protected function applySorting($query, $request, array $allowedSorts = [], string $defaultSort = 'created_at', string $defaultDirection = 'desc'): \Illuminate\Database\Eloquent\Builder
   {
       $sortBy = $request->get('sort_by', $defaultSort);
       $sortDir = $request->get('sort_dir', $defaultDirection);
       
       // Validate sort direction
       if (!in_array(strtolower($sortDir), ['asc', 'desc'])) {
           $sortDir = $defaultDirection;
       }
       
       // Check if sort field is allowed
       if (!empty($allowedSorts) && !in_array($sortBy, $allowedSorts)) {
           $sortBy = $defaultSort;
       }
       
       return $query->orderBy($sortBy, $sortDir);
   }

   /**
    * Get pagination parameters.
    *
    * @param  \Illuminate\Http\Request  $request
    * @param  int  $defaultPerPage
    * @param  int  $maxPerPage
    * @return array
    */
   protected function getPaginationParams($request, int $defaultPerPage = 15, int $maxPerPage = 100): array
   {
       $perPage = min($request->get('per_page', $defaultPerPage), $maxPerPage);
       $page = $request->get('page', 1);
       
       return [
           'per_page' => $perPage,
           'page' => $page,
       ];
   }

   /**
    * Handle bulk operations response.
    *
    * @param  int  $affectedCount
    * @param  string  $operation
    * @param  array  $errors
    * @return JsonResponse
    */
   protected function bulkOperationResponse(int $affectedCount, string $operation, array $errors = []): JsonResponse
   {
       $response = [
           'affected_count' => $affectedCount,
           'operation' => $operation,
       ];
       
       if (!empty($errors)) {
           $response['errors'] = $errors;
           $response['errors_count'] = count($errors);
       }
       
       $message = "Bulk {$operation} completed";
       
       if ($affectedCount > 0) {
           $message .= " - {$affectedCount} items processed";
       }
       
       if (!empty($errors)) {
           $message .= " with " . count($errors) . " errors";
       }
       
       return $this->successResponse($response, $message);
   }

   /**
    * Validate ownership of resource.
    *
    * @param  mixed  $resource
    * @param  string  $userIdField
    * @param  string  $message
    * @return void
    * @throws \Symfony\Component\HttpKernel\Exception\HttpException
    */
   protected function validateOwnership($resource, string $userIdField = 'user_id', string $message = 'You can only access your own resources'): void
   {
       $user = $this->getCurrentUser();
       
       if (!$user) {
           abort(401, 'Authentication required');
       }
       
       // Allow admins to access everything
       if ($user->hasRole('admin')) {
           return;
       }
       
       if ($resource->{$userIdField} !== $user->id) {
           abort(403, $message);
       }
   }

   /**
    * Create a resource response with location header.
    *
    * @param  mixed  $resource
    * @param  string  $routeName
    * @param  string  $message
    * @param  array  $routeParams
    * @return JsonResponse
    */
   protected function createdResponse($resource, string $routeName, string $message = 'Resource created successfully', array $routeParams = []): JsonResponse
   {
       $response = $this->successResponse($resource, $message, 201);
       
       // Add location header if route exists
       try {
           $location = route($routeName, $routeParams ?: ['id' => $resource->id]);
           $response->header('Location', $location);
       } catch (\Exception $e) {
           // Ignore if route doesn't exist
       }
       
       return $response;
   }

   /**
    * Handle soft delete response.
    *
    * @param  bool  $deleted
    * @param  string  $message
    * @return JsonResponse
    */
   protected function deleteResponse(bool $deleted, string $message = 'Resource deleted successfully'): JsonResponse
   {
       if ($deleted) {
           return $this->successResponse(null, $message);
       }
       
       return $this->errorResponse('Failed to delete resource', 500);
   }

   /**
    * Create cache key for API endpoint.
    *
    * @param  string  $prefix
    * @param  array  $params
    * @return string
    */
   protected function makeCacheKey(string $prefix, array $params = []): string
   {
       $key = 'api:' . $prefix;
       
       if (!empty($params)) {
           $key .= ':' . md5(serialize($params));
       }
       
       return $key;
   }

   /**
    * Get cached response or execute callback.
    *
    * @param  string  $cacheKey
    * @param  int  $ttl
    * @param  callable  $callback
    * @return mixed
    */
   protected function cacheResponse(string $cacheKey, int $ttl, callable $callback)
   {
       return cache()->remember($cacheKey, $ttl, $callback);
   }

   /**
    * Clear cache by pattern.
    *
    * @param  string  $pattern
    * @return void
    */
   protected function clearCachePattern(string $pattern): void
   {
       // This would depend on your cache driver
       // For Redis, you could use SCAN command
       // For now, we'll just forget the specific key
       cache()->forget($pattern);
   }
}