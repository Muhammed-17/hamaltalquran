<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GuardianSearchController extends Controller
{

    // ================================================================
    // التحقق من وجود حساب بالإيميل
    // ================================================================
    public function search(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $q = trim($request->get('q', ''));

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $user   = auth()->user();
        $fields = $this->getAllowedFields($user);

        $guardians = User::role('guardian')
            ->where(function ($query) use ($q, $user) {
                // ✅ البحث بالـ ID لو المدخل رقم
                if (is_numeric($q)) {
                    $query->where('id', (int) $q);
                    return;
                }

                // ✅ البحث بالاسم لكل الأدوار
                $query->where('name', 'like', "%{$q}%");

                // ✅ البحث بالإيميل فقط للأدوار المخولة
                if ($user->hasRole(['admin', 'general_manager', 'manager'])) {
                    $query->orWhere('email', 'like', "%{$q}%");
                }
            })
            ->select($fields)
            ->limit(10)
            ->get()
            ->map(fn($g) => $this->formatGuardian($g, $user));

        return response()->json($guardians);
    }

    // ================================================================
    // التحقق من وجود حساب بالإيميل أو الموبايل
    // ================================================================
    public function check(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $user  = auth()->user();
        $email = trim($request->get('email', ''));

        // ✅ supervisor لا يقدر يبحث بالإيميل
        if (!empty($email) && !$user->hasRole(['admin', 'general_manager', 'manager'])) {
            $email = '';
        }

        if (empty($email)) {
            return response()->json(['exists' => false]);
        }

        $guardian = User::role('guardian')
            ->where('email', $email)
            ->select($this->getAllowedFields($user))
            ->first();

        if (!$guardian) {
            return response()->json(['exists' => false]);
        }

        return response()->json([
            'exists' => true,
            ...$this->formatGuardian($guardian, $user),
        ]);
    }

    // ================================================================
    // Private Helpers
    // ================================================================

    /**
     * ✅ تحقق موحد من الصلاحية
     */
    private function authorizeAccess(): void
    {
        $user = auth()->user();

        $allowed =
            $user->hasRole('admin') ||
            $user->hasRole(['admin', 'general_manager', 'manager', 'supervisor']);

        if (!$allowed) {
            abort(403, 'ليس لديك صلاحية البحث عن أولياء الأمور');
        }
    }

    /**
     * ✅ الحقول المسموح بعرضها حسب الدور
     */
    private function getAllowedFields(User $user): array
    {
        // الحقول الأساسية لكل الأدوار
        $fields = ['id', 'name', 'status'];

        // ✅ الإيميل فقط للأدوار الإدارية
        if ($user->hasRole(['admin', 'general_manager', 'manager'])) {
            $fields[] = 'email';
        }

        return $fields;
    }

    /**
     * ✅ تنسيق بيانات الـ guardian حسب الدور
     */
    private function formatGuardian(User $guardian, User $currentUser): array
    {
        $data = [
            'id'        => $guardian->id,
            'name'      => $guardian->name,
            'status'    => $guardian->status,
        ];

        // ✅ الإيميل فقط للأدوار المخولة
        if ($currentUser->hasRole(['admin', 'general_manager', 'manager'])) {
            $data['email'] = $guardian->email ?? '';
        } else {
            $data['email'] = '';
        }

        return $data;
    }
}
