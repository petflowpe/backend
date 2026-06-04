<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\MercadoPagoService;
use App\Services\Payment\NiubizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function mercadoPago(Request $request, MercadoPagoService $service): JsonResponse
    {
        $companyId = (int) $request->query('company_id', $request->input('company_id', 0));
        if ($companyId <= 0) {
            $ref = $request->input('data.id') ?? $request->input('external_reference');
            if ($ref) {
                $payment = Payment::where('reference', 'like', '%' . $ref . '%')->first();
                $companyId = (int) ($payment?->company_id ?? 0);
            }
        }

        if ($companyId <= 0) {
            return response()->json(['success' => false], 400);
        }

        $payment = $service->handleWebhook($companyId, $request->all());

        return response()->json(['success' => true, 'payment_id' => $payment?->id]);
    }

    public function niubiz(Request $request, NiubizService $service): JsonResponse
    {
        $purchaseNumber = $request->input('purchaseNumber')
            ?? $request->input('purchase_number')
            ?? $request->input('transactionId');

        if (!$purchaseNumber) {
            return response()->json(['success' => false], 400);
        }

        $payment = Payment::where('gateway', 'niubiz')
            ->where(function ($q) use ($purchaseNumber) {
                $q->where('external_id', $purchaseNumber)
                    ->orWhere('reference', 'like', '%' . $purchaseNumber . '%');
            })
            ->first();

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Pago no encontrado'], 404);
        }

        $service->markCompletedFromWebhook($payment, $request->all());

        return response()->json(['success' => true, 'payment_id' => $payment->id]);
    }
}
