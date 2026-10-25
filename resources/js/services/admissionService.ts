import { get, post, put, API_ENDPOINTS } from '../api';
import {
  Admission,
  AdmissionFormData,
  AdmissionFilters,
  ApiMeta,
} from '../types';
import { studentService } from './studentService';

/**
 * Service for Online Admissions and Inquiries operations.
 */
export const admissionService = {
  /**
   * Retrieve paginated admission inquiries with search and filter parameters.
   */
  async getAdmissions(filters?: AdmissionFilters): Promise<{ data: Admission[]; meta?: ApiMeta }> {
    const params: Record<string, unknown> = {};

    if (filters) {
      if (filters.search) {
        params.search = filters.search;
      }

      if (filters.courseId !== undefined && filters.courseId !== null && !isNaN(filters.courseId)) {
        params.courseId = filters.courseId;
      }

      if (filters.courseType !== undefined && filters.courseType !== null && filters.courseType !== 'all') {
        params.courseType = filters.courseType;
      }

      if (filters.isActive !== undefined && filters.isActive !== null && filters.isActive !== 'all') {
        params.isActive = filters.isActive;
      }

      if (filters.page) params.page = filters.page;
      if (filters.per_page) params.per_page = filters.per_page;
    }

    const response = await get<Admission[]>(API_ENDPOINTS.academic.admissions, { params });
    return {
      data: response.data,
      meta: response.meta,
    };
  },

  /**
   * Retrieve single admission inquiry record by ID.
   */
  async getAdmission(id: number | string): Promise<Admission> {
    const response = await get<Admission>(`${API_ENDPOINTS.academic.admissions}/${id}`);
    return response.data;
  },

  /**
   * Submit an online admission inquiry (used by public form or admin inquiry creation).
   */
  async submitAdmission(data: AdmissionFormData): Promise<Admission> {
    const payload: Record<string, unknown> = { ...data };

    if (payload.courseId === '') delete payload.courseId;
    if (payload.courseType === '') delete payload.courseType;
    if (!payload.email || payload.email === '') delete payload.email;

    const response = await post<Admission>(API_ENDPOINTS.academic.admissions, payload);
    return response.data;
  },

  /**
   * Administrative update of an existing admission inquiry record.
   */
  async updateAdmission(id: number | string, data: Partial<AdmissionFormData>): Promise<Admission> {
    const payload: Record<string, unknown> = { ...data };

    if (payload.courseId === '') delete payload.courseId;
    if (payload.courseType === '') delete payload.courseType;
    if (payload.email === '') delete payload.email;

    const response = await put<Admission>(`${API_ENDPOINTS.academic.admissions}/${id}`, payload);
    return response.data;
  },

  /**
   * Quickly update an admission inquiry's lifecycle status code (0-6).
   */
  async updateStatus(id: number | string, isActive: number): Promise<Admission> {
    const response = await put<Admission>(`${API_ENDPOINTS.academic.admissions}/${id}`, {
      isActive,
    });
    return response.data;
  },

  /**
   * Guard: Check if a registered student with this phone number already exists
   * before converting/enrolling an admission inquiry into a student record.
   */
  async checkExistingStudent(phone: string): Promise<boolean> {
    if (!phone || phone.trim() === '') return false;
    try {
      const res = await studentService.getStudents({ search: phone.trim() });
      if (res.data && res.data.length > 0) {
        // Confirm matching phone
        return res.data.some((s) => s.phone && s.phone.trim() === phone.trim());
      }
      return false;
    } catch {
      return false;
    }
  },
};
