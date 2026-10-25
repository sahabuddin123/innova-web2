/**
 * INNOVA WEB — Student Management TypeScript Contracts
 *
 * Models and request interfaces matching Laravel Eloquent models and ApiController envelopes.
 */

export interface StudentCourseBrief {
  id: number;
  name: string;
  shortTitle?: string | null;
  coursefee?: string | number;
  courseduration?: string | null;
  totalmoduls?: string | null;
  totalproject?: string | null;
}

export interface StudentBatchBrief {
  id: number;
  batchnum: string;
  totalseat?: string | null;
  startdate?: string | null;
  enddate?: string | null;
}

export interface StudentPaymentBrief {
  id: number;
  student_id: number;
  amount?: string | null;
  paidamount?: string | number;
  paymentType?: number;
  created_at?: string;
}

export interface Student {
  id: number;
  user_id?: number | null;
  course_id: number;
  batch_id: number;
  branch_id?: number | null;
  sname: string;
  gender: string;
  dob?: string | null;
  bloodgroup?: string | null;
  religion?: string | null;
  height?: string | null;
  weight?: string | null;
  phone?: string | null;
  nid?: string | null;
  last_school?: string | null;
  f_name?: string | null;
  f_phone?: string | null;
  m_name?: string | null;
  m_phone?: string | null;
  g_name: string;
  g_phone: string;
  rel_g: string;
  discount?: string | number | null;
  course_fee: string | number;
  balance?: string | number | null;
  ref?: string | null;
  image?: string | null;
  admission_date: string;
  IsRegister?: number; // 0 or 1
  IsActive: number; // 1 = Active, 2 = Irregular, 3 = Complete, 4 = Restricted, 5 = Postponed
  created_at?: string;
  updated_at?: string;
  course?: StudentCourseBrief | null;
  batch?: StudentBatchBrief | null;
  latestPayment?: StudentPaymentBrief | null;
}

export interface StudentFormData {
  sname: string;
  gender: string;
  religion: string;
  phone?: string;
  course_id: number | '';
  batch_id: number | '';
  branch_id?: number | '';
  course_fee: string;
  discount?: string;
  balance?: string;
  admission_date: string;
  g_name: string;
  g_phone: string;
  rel_g: string;
  dob?: string;
  bloodgroup?: string;
  height?: string;
  weight?: string;
  nid?: string;
  last_school?: string;
  f_name?: string;
  f_phone?: string;
  m_name?: string;
  m_phone?: string;
  ref?: string;
  image?: string;
  IsRegister?: number;
  IsActive?: number;
}

export interface StudentFilters {
  search?: string;
  q?: string;
  course_id?: number;
  batch_id?: number;
  is_active?: number;
  status?: 'all' | 'active' | 'irregular' | 'complete' | 'restricted' | 'postponed';
  per_page?: number;
  page?: number;
}

export interface StudentStatusMeta {
  code: number;
  label: string;
  badge: string;
  description: string;
}

export const STUDENT_STATUS_MAP: Record<number, StudentStatusMeta> = {
  1: {
    code: 1,
    label: 'Active',
    badge: 'innova-badge-success',
    description: 'Regular enrolled student actively attending classes',
  },
  2: {
    code: 2,
    label: 'Irregular',
    badge: 'innova-badge-warning',
    description: 'Student with attendance or curriculum delays',
  },
  3: {
    code: 3,
    label: 'Complete',
    badge: 'innova-badge-primary',
    description: 'Graduated / completed course modules',
  },
  4: {
    code: 4,
    label: 'Restricted',
    badge: 'innova-badge-error',
    description: 'Restricted access due to administrative hold',
  },
  5: {
    code: 5,
    label: 'Postponed',
    badge: 'innova-badge-gray',
    description: 'Enrollment temporarily postponed or deferred',
  },
};

export const GENDER_OPTIONS = ['Male', 'Female', 'Other'] as const;

export const RELIGION_OPTIONS = ['Islam', 'Hinduism', 'Christianity', 'Buddhism', 'Other'] as const;

export const BLOOD_GROUP_OPTIONS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as const;

export const GUARDIAN_RELATION_OPTIONS = ['Father', 'Mother', 'Brother', 'Sister', 'Uncle', 'Aunt', 'Spouse', 'Guardian', 'Other'] as const;
