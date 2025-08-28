<?php

namespace App\Http\Controllers\Cashier\Auth;

use App\Models\Bill;
use Inertia\Inertia;
use App\Models\UserShift;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\CashRegisterSession;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request)
    {
        return Inertia::render('cashier/Login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            if (Auth::user()->role_id !== 3) {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'Bạn không phải là thu ngân.',
                ]);
            }

            $request->session()->regenerate();
            return redirect()->intended(route('cashier.dashboard'));
        }

        return back()->withErrors([
            'email' => 'Email hoặc mật khẩu không đúng.',
        ]);
    }

    public function destroy(Request $request)
    {
        $closeShift = $request->input('closeShift', false);

        if ($closeShift) {
            // Đóng ca trong cash_register_sessions
            $currentSession = CashRegisterSession::where('user_id', Auth::id())
                ->whereNull('closed_at')
                ->first();

            if ($currentSession) {
                // Tính closing_amount từ bills (tổng tiền giao dịch trong ca)
                $closingAmount = Bill::where('session_id', $currentSession->id)
                    ->sum('total_amount');

                $currentSession->update([
                    'closed_at' => Carbon::now(),
                    'closing_amount' => $closingAmount,
                    'actual_amount' => $closingAmount, // Giả sử actual_amount bằng closing_amount, có thể cần nhập từ frontend
                    'difference' => 0,
                    'notes' => 'Ca làm việc được đóng khi đăng xuất.',
                ]);

                // Cập nhật user_shifts
                $currentShift = UserShift::where('user_id', Auth::id())
                    ->where('date', Carbon::today())
                    ->whereIn('status', ['SCHEDULED', 'CHECKED_IN'])
                    ->first();

                if ($currentShift) {
                    $checkOutTime = Carbon::now();
                    $totalHours = $currentShift->check_in
                        ? $checkOutTime->diffInHours($currentShift->check_in)
                        : null;

                    $currentShift->update([
                        'status' => 'COMPLETED',
                        'check_out' => $checkOutTime,
                        'total_hours' => $totalHours,
                        'notes' => 'Ca làm việc hoàn thành khi đăng xuất.',
                    ]);
                }
            }
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/cashier/login')
            ->with('success', 'Đăng xuất thành công!');
    }
}
