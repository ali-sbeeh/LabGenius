<?php

// app/Http/Controllers/Api/ApiController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiBaseController extends Controller
{
    use ApiResponseTrait;

    /**
     * Current authenticated user
     *
     * @var \App\Models\User|null
     */
    protected $user;

    /**
     * Current authenticated user ID
     *
     * @var int|null
     */
    protected $userId;

    /**
     * Create a new controller instance.
     */
    public function __construct(Request $request)
    {
        $this->user = $request->user();
        $this->userId = $this->user?->id;
    }

    /**
     * Validate request data and return response on failure
     */
    protected function validateRequest(Request $request, array $rules, array $messages = [])
    {
        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        return null;
    }

    /**
     * Get pagination parameters from request
     */
    protected function getPaginationParams(Request $request): array
    {
        return [
            'per_page' => $request->get('per_page', 15),
            'page' => $request->get('page', 1),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
        ];
    }

    /**
     * Apply sorting to query builder
     */
    protected function applySorting($query, array $params, array $allowedSortFields)
    {
        $sortBy = $params['sort_by'];
        $sortOrder = $params['sort_order'];

        if (in_array($sortBy, $allowedSortFields) && in_array($sortOrder, ['asc', 'desc'])) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query;
    }
}
