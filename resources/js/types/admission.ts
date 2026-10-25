/**
 * INNOVA WEB — Online Student Admissions TypeScript Contracts
 *
 * Models and interfaces matching admissions database table,
 * Form Requests, and AdmissionApiController REST responses.
 */

export interface AdmissionCourseBrief {
  id: number;
  name: string;
  shortTitle?: string | null;
  coursefee?: string | number | null;
}

export interface Admission {
  id: number;
  username: string; // applicant full name
  phone: string;
  email?: string | null;
  courseId: number;
  courseType: number; // 0 = Offline, 1 = Online
  isActive: number; // 0: Waiting for Contact, 1: Call Not Received, 2: Waiting For Admission, 3: Admission Complete, 4: SMS Sent, 5: Not Interested, 6: Restricted
  created_at: string;
  updated_at: string;
  course?: AdmissionCourseBrief | null;
}

export interface AdmissionFormData {
  username: string;
  phone: string;
  email?: string;
  courseId: number | '';
  courseType: number | '';
  isActive?: number;
}

export interface AdmissionFilters {
  search?: string;
  courseId?: number;
  courseType?: number | 'all';
  isActive?: number | 'all';
  page?: number;
  per_page?: number;
}

export interface AdmissionStatusMeta {
  code: number;
  label: string;
  badgeClass: string;
  description: string;
}

export const ADMISSION_STATUS_MAP: Record<number, AdmissionStatusMeta> = {
  0: {
    code: 0,
    label: 'Waiting for Contact',
    badgeClass: 'innova-badge-warning',
    description: 'New inquiry received; waiting for representative follow-up call.',
  },
  1: {
    code: 1,
    label: 'Call Not Received',
    badgeClass: 'innova-badge-info',
    description: 'Representative called applicant, but the call was not answered.',
  },
  2: {
    code: 2,
    label: 'Waiting For Admission',
    badgeClass: 'innova-badge-success',
    description: 'Applicant communicated interest and is preparing enrollment documents.',
  },
  3: {
    code: 3,
    label: 'Admission Complete',
    badgeClass: 'innova-badge-primary',
    description: 'Applicant has completed admission formalities and is enrolled.',
  },
  4: {
    code: 4,
    label: 'SMS Sent',
    badgeClass: 'innova-badge-info',
    description: 'Course details, schedule, or payment links dispatched via SMS.',
  },
  5: {
    code: 5,
    label: 'Not Interested',
    badgeClass: 'innova-badge-gray',
    description: 'Applicant declined admission or is not currently interested.',
  },
  6: {
    code: 6,
    label: 'Restricted',
    badgeClass: 'innova-badge-error',
    description: 'Applicant profile marked as restricted or blacklisted.',
  },
};

export const COURSE_TYPE_MAP: Record<number, { label: string; badgeClass: string }> = {
  0: { label: 'Offline', badgeClass: 'innova-badge-primary' },
  1: { label: 'Online', badgeClass: 'innova-badge-info' },
};
