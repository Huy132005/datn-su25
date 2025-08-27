<?php

namespace App\Http\Controllers\Admin;

use App\Models\BatchItem;
use Inertia\Inertia;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\ProductSupplier;
use App\Models\ProductUnit;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'unit', 'suppliers', 'batchItems'])->get();
        $categories = Category::all();
        $units = ProductUnit::all();
        $suppliers = ProductSupplier::all();
        $all_suppliers = Supplier::all();
        $availableProducts = [];

        foreach ($products as $product) {
            $totalQuantity = $product->getCurrentStock();

            if ($totalQuantity > 0) {
                $availableProducts[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'value' => $totalQuantity,
                ];
            }
        }

        return Inertia::render('admin/inventory/Index')->with([
            'products' => $products,
            'categories' => $categories,
            'units' => $units,
            'suppliers' => $suppliers,
            'allSuppliers' => $all_suppliers,
            'availableProducts' => $availableProducts,
        ]);
    }
    public function show($id)
    {
        $product = Product::with(['category', 'unit', 'suppliers'])->find($id);

        $availableProducts = [];
        $totalQuantity = $product->getCurrentStock();
        if ($totalQuantity > 0) {
            $availableProducts[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'value' => $totalQuantity,
            ];
        }

        return Inertia::render('admin/inventory/Show')->with([
            'product' => $product,
            'unit' => $product->unit,
            'category' => $product->category,
            'batch' => $product->batches,
            'batchItems' => $product->batchItems,
            'availableProducts' => $availableProducts,
        ]);
    }
    public function update(Request $request, $id) {}

    public function adjustInventory(Request $request, $batch_item_id)
    {
        // dd($request->all(), $batch_item_id);
        // Validate batch_item_id exists
        if (!BatchItem::where('id', $batch_item_id)->exists()) {
            return redirect()->back()->with('error', 'Lô hàng không tồn tại.');
        }

        $request->validate([
            'new_quantity' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:255'
        ]);

        try {
            return DB::transaction(function () use ($request, $batch_item_id) {
                $batchItem = BatchItem::with('batch')->findOrFail($batch_item_id);
                $product = Product::findOrFail($batchItem->product_id);

                $min_stock = $product->min_stock_level;
                $max_stock = $product->max_stock_level;
                $oldQuantity = $batchItem->current_quantity;
                $newQuantity = $request->new_quantity;
                $quantityChange = '';

                if ($newQuantity >= $max_stock) {
                    return redirect()->back()->with('error', 'Số lượng thay đổi vượt quá định mức cho phép!');
                }
                if ($newQuantity <= $min_stock) {
                    return redirect()->back()->with('error', 'Số lượng thay đổi không đạt số lượng tối thiểu!');
                }

                if ($newQuantity > $oldQuantity && $newQuantity <= $max_stock) {
                    $batchItem->current_quantity = $newQuantity;
                    $quantityChange = $newQuantity - $oldQuantity;
                    $batchItem->save();

                    InventoryTransaction::create([
                        'transaction_type_id' => 3,
                        'product_id' => $product->id,
                        'quantity_change' => $quantityChange,
                        'stock_after' => $newQuantity,
                        'transaction_date' => now(),
                        'related_batch_id' => $batchItem->batch_id,
                        'user_id' => Auth::id(),
                        'note' => $request->reason . " (Lô: " . $batchItem->batch->batch_number . ")",
                    ]);
                }

                if ($newQuantity < $oldQuantity && $newQuantity >= $min_stock) {
                    $batchItem->current_quantity = $newQuantity;
                    $quantityChange = $oldQuantity - $newQuantity;
                    $batchItem->save();

                    InventoryTransaction::create([
                        'transaction_type_id' => 3,
                        'product_id' => $product->id,
                        'quantity_change' => $quantityChange,
                        'stock_after' => $newQuantity,
                        'transaction_date' => now(),
                        'related_batch_id' => $batchItem->batch_id,
                        'user_id' => Auth::id(),
                        'note' => $request->reason . " (Lô: " . $batchItem->batch->batch_number . ")",
                    ]);
                }


                $this->syncInventory();

                return redirect()->back()->with('success', 'Điều chỉnh tồn kho lô hàng thành công!');
            });
        } catch (\Exception $e) {
            Log::error('Error adjusting inventory: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Có lỗi khi điều chỉnh tồn kho lô hàng.');
        }
    }

    public function syncInventory()
    {
        try {
            return DB::transaction(function () {
                $updatedCount = 0;
                $products = Product::where('is_active', true)
                    ->whereNull('deleted_at')
                    ->select('id', 'stock_quantity')
                    ->get();

                foreach ($products as $product) {
                    $query = BatchItem::where('product_id', $product->id)
                        ->whereIn('inventory_status', ['active', 'expiring_soon'])
                        ->where('current_quantity', '>', 0)
                        ->whereHas('batch', function ($q) {
                            $q->whereNull('deleted_at')
                                ->where('receipt_status', 'completed');
                        })
                        ->where(function ($q) {
                            $q->whereNull('expiry_date')
                                ->orWhere('expiry_date', '>=', Carbon::today('Asia/Ho_Chi_Minh'));
                        });

                    $totalBatchQuantity = $query->sum('current_quantity');

                    if ($product->stock_quantity != $totalBatchQuantity) {
                        $product->stock_quantity = $totalBatchQuantity;
                        $product->save();
                        $updatedCount++;
                    }
                }

                return redirect()->route('admin.inventory.index')->with('success', 'Đồng bộ tồn kho hoàn tất với ' . $updatedCount . ' sản phẩm được update!');

                // return response()->json([
                //     'message' => 'Đồng bộ tồn kho hoàn tất!',
                //     'updated_products' => $updatedCount
                // ], 200);
            });
        } catch (\Exception $e) {
            // return response()->json([
            //     'errors' => ['server' => 'Có lỗi khi đồng bộ tồn kho.']
            // ], 500);
            return redirect()->route('admin.inventory.index')->with('error', 'Có lỗi khi đồng bộ tồn kho.');
        }
    }
}
