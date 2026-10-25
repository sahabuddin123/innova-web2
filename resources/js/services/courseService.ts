import { get, post, put, del, API_ENDPOINTS } from '../api';
import {
  Course,
  CourseFormData,
  CourseFilters,
  CourseCategory,
  Teacher,
  ApiMeta,
} from '../types';

/**
 * Service for Course and Category academic catalog operations.
 */
export const courseService = {
  /**
   * Retrieve paginated courses with optional filters.
   */
  async getCourses(filters?: CourseFilters): Promise<{ data: Course[]; meta?: ApiMeta }> {
    const params: Record<string, unknown> = {};
    if (filters) {
      if (filters.search) params.search = filters.search;
      if (filters.category_id !== undefined && filters.category_id !== null && !isNaN(filters.category_id)) {
        params.category_id = filters.category_id;
      }
      if (filters.is_active !== undefined && filters.is_active !== null) {
        params.is_active = filters.is_active;
      }
      if (filters.featured !== undefined && filters.featured !== null) {
        params.featured = filters.featured;
      }
      if (filters.page) params.page = filters.page;
      if (filters.per_page) params.per_page = filters.per_page;
    }

    const response = await get<Course[]>(API_ENDPOINTS.academic.courses, { params });
    return {
      data: response.data,
      meta: response.meta,
    };
  },

  /**
   * Retrieve a single course with details and category.
   */
  async getCourse(id: number): Promise<Course> {
    const response = await get<Course>(`${API_ENDPOINTS.academic.courses}/${id}`);
    return response.data;
  },

  /**
   * Create a new course record.
   */
  async createCourse(data: CourseFormData): Promise<Course> {
    const response = await post<Course>(API_ENDPOINTS.academic.courses, data);
    return response.data;
  },

  /**
   * Update an existing course record.
   */
  async updateCourse(id: number, data: Partial<CourseFormData>): Promise<Course> {
    const response = await put<Course>(`${API_ENDPOINTS.academic.courses}/${id}`, data);
    return response.data;
  },

  /**
   * Delete a course record. (Blocked if enrolled students are linked).
   */
  async deleteCourse(id: number): Promise<void> {
    await del<null>(`${API_ENDPOINTS.academic.courses}/${id}`);
  },

  /**
   * Retrieve course categories for selection dropdowns.
   */
  async getCategories(): Promise<CourseCategory[]> {
    const response = await get<CourseCategory[]>(API_ENDPOINTS.academic.categories);
    return response.data;
  },

  /**
   * Retrieve active teachers/instructors for instructor dropdowns.
   */
  async getTeachers(): Promise<Teacher[]> {
    try {
      const response = await get<Teacher[]>(API_ENDPOINTS.academic.teachers);
      return response.data;
    } catch {
      return [];
    }
  },
};
