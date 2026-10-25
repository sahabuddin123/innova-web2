import { get, post, put, del, API_ENDPOINTS } from '../api';
import {
  Student,
  StudentFormData,
  StudentFilters,
  ApiMeta,
} from '../types';

/**
 * Service for Student Management administrative operations.
 */
export const studentService = {
  /**
   * Retrieve paginated students with search and filter parameters.
   */
  async getStudents(filters?: StudentFilters): Promise<{ data: Student[]; meta?: ApiMeta }> {
    const params: Record<string, unknown> = {};

    if (filters) {
      if (filters.search) {
        params.search = filters.search;
      } else if (filters.q) {
        params.q = filters.q;
      }

      if (filters.course_id !== undefined && filters.course_id !== null && !isNaN(filters.course_id)) {
        params.course_id = filters.course_id;
      }

      if (filters.batch_id !== undefined && filters.batch_id !== null && !isNaN(filters.batch_id)) {
        params.batch_id = filters.batch_id;
      }

      if (filters.is_active !== undefined && filters.is_active !== null && !isNaN(filters.is_active)) {
        params.is_active = filters.is_active;
      } else if (filters.status && filters.status !== 'all') {
        params.status = filters.status;
      }

      if (filters.page) params.page = filters.page;
      if (filters.per_page) params.per_page = filters.per_page;
    }

    const response = await get<Student[]>(API_ENDPOINTS.academic.students, { params });
    return {
      data: response.data,
      meta: response.meta,
    };
  },

  /**
   * Retrieve a single student's complete record including course and batch.
   */
  async getStudent(id: number | string): Promise<Student> {
    const response = await get<Student>(`${API_ENDPOINTS.academic.students}/${id}`);
    return response.data;
  },

  /**
   * Create a new student enrollment record.
   */
  async createStudent(data: StudentFormData): Promise<Student> {
    const payload: Record<string, unknown> = { ...data };

    // Clean up empty optional values
    if (payload.branch_id === '') delete payload.branch_id;
    if (payload.discount === '') delete payload.discount;
    if (payload.balance === '') delete payload.balance;
    if (payload.dob === '') delete payload.dob;
    if (payload.bloodgroup === '') delete payload.bloodgroup;

    const response = await post<Student>(API_ENDPOINTS.academic.students, payload);
    return response.data;
  },

  /**
   * Update an existing student record.
   */
  async updateStudent(id: number | string, data: Partial<StudentFormData>): Promise<Student> {
    const payload: Record<string, unknown> = { ...data };

    if (payload.branch_id === '') delete payload.branch_id;
    if (payload.discount === '') delete payload.discount;
    if (payload.balance === '') delete payload.balance;
    if (payload.dob === '') delete payload.dob;
    if (payload.bloodgroup === '') delete payload.bloodgroup;

    const response = await put<Student>(`${API_ENDPOINTS.academic.students}/${id}`, payload);
    return response.data;
  },

  /**
   * Quickly update a student's active status (1 = Active, 2 = Irregular, 3 = Complete, 4 = Restricted, 5 = Postponed).
   */
  async updateStatus(id: number | string, isActive: number): Promise<Student> {
    const response = await put<Student>(`${API_ENDPOINTS.academic.students}/${id}`, {
      IsActive: isActive,
    });
    return response.data;
  },

  /**
   * Soft-delete a student record (prevented if payment records exist).
   */
  async deleteStudent(id: number | string): Promise<void> {
    await del(`${API_ENDPOINTS.academic.students}/${id}`);
  },
};
