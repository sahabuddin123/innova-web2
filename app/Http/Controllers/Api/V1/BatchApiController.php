<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreBatchRequest;
use App\Http\Requests\Api\V1\UpdateBatchRequest;
use App\Models\Batchs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class BatchApiController
 *
 * REST API controller managing academic batches under /api/v1/academic/batches.
 *
 * @package App\Http\Controllers\Api\V1
 */
class BatchApiController extends ApiController
{
    /**
     * Display a listing of academic batches.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Batchs::whereNull('deleted_at');

        if (\Illuminate\Support\Facades\Schema::hasTable('teachers')) {
            $query->with('teacher');
        }

        if ($request->has('is_active')) {
            $query->where('IsActive', (int) $request->input('is_active'));
        } elseif ($request->filled('status')) {
            $statusMap = [
                'postponed' => 0,
                'running'   => 1,
                'upcoming'  => 2,
                'completed' => 3,
            ];
            $status = strtolower($request->input('status'));
            if (array_key_exists($status, $statusMap)) {
                $query->where('IsActive', $statusMap[$status]);
            }
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('batchnum', 'like', "%{$search}%");
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $paginated = $query->orderBy('id', 'asc')->paginate($perPage);

        return $this->success($paginated->items(), 'Academic batches retrieved successfully', 200, [
            'current_page' => $paginated->currentPage(),
            'per_page'     => $paginated->perPage(),
            'total'        => $paginated->total(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    /**
     * Display the specified academic batch.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $query = Batchs::whereNull('deleted_at');

        if (\Illuminate\Support\Facades\Schema::hasTable('teachers')) {
            $query->with('teacher');
        }

        $batch = $query->find($id);

        if (!$batch) {
            return $this->error('Academic batch not found', 404);
        }

        return $this->success($batch, 'Academic batch retrieved successfully');
    }

    /**
     * Store a newly created academic batch.
     *
     * @param StoreBatchRequest $request
     * @return JsonResponse
     */
    public function store(StoreBatchRequest $request): JsonResponse
    {
        $batch = Batchs::create($request->validated());

        return $this->success($batch, 'Academic batch created successfully', 201);
    }

    /**
     * Update the specified academic batch.
     *
     * @param UpdateBatchRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateBatchRequest $request, $id): JsonResponse
    {
        $batch = Batchs::whereNull('deleted_at')->find($id);

        if (!$batch) {
            return $this->error('Academic batch not found', 404);
        }

        $batch->update($request->validated());

        return $this->success($batch, 'Academic batch updated successfully');
    }

    /**
     * Remove the specified academic batch.
     * Prevents deletion if students are assigned to this batch.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $batch = Batchs::whereNull('deleted_at')->find($id);

        if (!$batch) {
            return $this->error('Academic batch not found', 404);
        }

        if (method_exists($batch, 'StudentInfo') && $batch->StudentInfo()->count() > 0) {
            return $this->error('Cannot delete batch: enrolled students are assigned to this batch', 422);
        }

        $batch->delete();

        return $this->success(null, 'Academic batch deleted successfully');
    }
}
