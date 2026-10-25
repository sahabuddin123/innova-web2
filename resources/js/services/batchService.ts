import { get, post, put, del, API_ENDPOINTS } from '../api';
import {
  Batch,
  BatchFormData,
  BatchFilters,
  ApiMeta,
} from '../types';

/**
 * Service for Academic Batch REST operations.
 */
export const batchService = {
  /**
   * Retrieve paginated batches with optional filters (search, status).
   */
  async getBatches(filters?: BatchFilters): Promise<{ data: Batch[]; meta?: ApiMeta }> {
    const params: Record<string, unknown> = {};
    if (filters) {
      if (filters.search) params.search = filters.search;
      if (filters.status && filters.status !== 'all') {
        params.status = filters.status;
      }
      if (filters.is_active !== undefined && filters.is_active !== null) {
        params.is_active = filters.is_active;
      }
      if (filters.page) params.page = filters.page;
      if (filters.per_page) params.per_page = filters.per_page;
    }

    const response = await get<Batch[]>(API_ENDPOINTS.academic.batches, { params });
    return {
      data: response.data,
      meta: response.meta,
    };
  },

  /**
   * Retrieve a single batch by ID.
   */
  async getBatch(id: number): Promise<Batch> {
    const response = await get<Batch>(`${API_ENDPOINTS.academic.batches}/${id}`);
    return response.data;
  },

  /**
   * Create a new academic batch.
   */
  async createBatch(data: BatchFormData): Promise<Batch> {
    const response = await post<Batch>(API_ENDPOINTS.academic.batches, data);
    return response.data;
  },

  /**
   * Update an existing academic batch.
   */
  async updateBatch(id: number, data: Partial<BatchFormData>): Promise<Batch> {
    const response = await put<Batch>(`${API_ENDPOINTS.academic.batches}/${id}`, data);
    return response.data;
  },

  /**
   * Delete an academic batch. (Blocked if assigned students exist).
   */
  async deleteBatch(id: number): Promise<void> {
    await del<null>(`${API_ENDPOINTS.academic.batches}/${id}`);
  },
};
