<?php

namespace App\Http\Controllers\Cashier;

use Carbon\Carbon;
use App\Models\Bill;
use App\Models\User;
use Inertia\Inertia;
use App\Models\Product;
use App\Models\BatchItem;
use App\Models\UserShift;
use App\Models\BillDetail;
use Illuminate\Http\Request;
use App\Models\CashRegisterSession;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Notifications\ProductAddedToCart;
use App\Notifications\StockReplenishmentRequest;

class CashierDashboardController extends Controller
{
    public function index(Request $request)
    {
        $now = Carbon::now();
        $currentCashier = 'Chưa có nhân viên trực';
        $currentShift = 'Chưa xác định ca';
        $shiftRevenue = [];
        $shiftBills = [];
        $todayOrders = 0;
        $todayRevenue = 0;
        $todayBills = [];
        $totalStock = 0;
        $allProducts = [];

        // Kiểm tra phiên quầy thu ngân đang mở
        $activeSession = CashRegisterSession::whereNull('closed_at')
            ->where('user_id', Auth::id())
            ->with('user')
            ->first();

        if ($activeSession) {
            $currentCashier = $activeSession->user->name;

            // Lấy ca làm việc liên quan đến phiên quầy thu ngân
            $currentShiftRecord = UserShift::where('user_id', Auth::id())
                ->where('status', 'CHECKED_IN')
                ->whereNotNull('check_in')
                ->where(function ($query) use ($now) {
                    $query->whereNull('check_out')
                        ->orWhere('check_out', '>=', $now);
                })
                ->with('user')
                ->first();

            if ($currentShiftRecord) {
                $currentShift = 'Ca của ' . $currentShiftRecord->user->name;

                // Tính toán doanh thu và hóa đơn trong khoảng thời gian của ca
                $startTime = Carbon::parse($currentShiftRecord->check_in);
                $endTime = $currentShiftRecord->check_out ? Carbon::parse($currentShiftRecord->check_out) : $now;

                $todayRevenue = Bill::whereBetween('created_at', [$startTime, $endTime])
                    ->sum('total_amount');

                $todayOrders = Bill::whereBetween('created_at', [$startTime, $endTime])
                    ->count();

                $todayBills = Bill::whereBetween('created_at', [$startTime, $endTime])
                    ->with('customer')
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($bill) {
                        return [
                            'bill_number' => $bill->bill_number,
                            'customer_name' => $bill->customer ? $bill->customer->customer_name : 'Khách lẻ',
                            'total_amount' => number_format($bill->total_amount, 0, ',', '.') . 'đ',
                            'created_at' => $bill->created_at->format('H:i'),
                        ];
                    })->toArray();

                $shiftRevenue = [
                    [
                        'shift_name' => $currentShift,
                        'revenue' => number_format($todayRevenue, 0, ',', '.') . 'đ',
                    ]
                ];
                $shiftBills = [
                    [
                        'shift_name' => $currentShift,
                        'bills' => $todayBills,
                    ]
                ];
            } else {
                Log::info('No active shift found for user ' . Auth::id() . ' in active cash register session');
            }

            // Tính tổng tồn kho
            $totalStock = BatchItem::sum('current_quantity');

            // Lấy danh sách sản phẩm với thông tin tồn kho và số lượng bán
            $allProducts = Product::select('id', 'name', 'sku', 'selling_price')
                ->with(['billDetails' => function ($query) use ($startTime, $endTime) {
                    $query->select('product_id')
                        ->selectRaw('SUM(quantity) as total_quantity')
                        ->whereBetween('created_at', [$startTime, $endTime])
                        ->groupBy('product_id');
                }])
                ->get()
                ->map(function ($product) use ($now) {
                    $stock = BatchItem::where('product_id', $product->id)
                        ->where('created_at', '<=', $now)
                        ->sum('current_quantity');

                    $totalQuantity = $product->billDetails->first()->total_quantity ?? 0;

                    return [
                        'id' => $product->id,
                        'name' => trim($product->name),
                        'sku' => trim($product->sku),
                        'stock' => (int) $stock,
                        'price' => number_format($product->selling_price, 0, ',', '.') . 'đ',
                        'total_quantity' => (int) $totalQuantity,
                    ];
                })->toArray();
        } else {
            Log::info('No active cash register session found for user ' . Auth::id());
        }

        return Inertia::render('cashier/Dashboard', [
            'todayRevenue' => number_format($todayRevenue, 0, ',', '.') . 'đ',
            'todayOrders' => $todayOrders,
            'totalStock' => $totalStock,
            'currentCashier' => $currentCashier,
            'currentShift' => $currentShift,
            'todayBills' => $todayBills,
            'allProducts' => $allProducts,
            'shiftRevenue' => $shiftRevenue,
            'shiftBills' => $shiftBills,
        ]);
    }

    public function requestStock(Request $request)
    {
        $productId = $request->input('product_id');
        $quantity = $request->input('quantity', 1);

        $product = Product::findOrFail($productId);
        $cashier = Auth::user();

        // Kiểm tra xem có phiên quầy thu ngân đang mở hay không
        $activeSession = CashRegisterSession::whereNull('closed_at')
            ->where('user_id', Auth::id())
            ->first();

        if (!$activeSession) {
            return response()->json([
                'message' => 'Không thể gửi yêu cầu nhập hàng vì không có phiên quầy thu ngân đang mở!',
            ], 403);
        }

        // Gửi thông báo đến tất cả các admin
        $admins = User::where('role_id', 1)->get();
        foreach ($admins as $admin) {
            $admin->notify(new StockReplenishmentRequest($cashier, [
                'name' => $product->name,
                'sku' => $product->sku,
                'quantity' => $quantity,
            ]));
        }

        return response()->json([
            'message' => "Yêu cầu nhập thêm {$quantity} sản phẩm '{$product->name}' đã được gửi đến admin!",
        ]);
    }
}