<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\AddressRequest;
use App\Libraries\Member\AddressLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 고객 배송지록. 회원 전용이다.
 */
class AddressController extends Controller
{
    public function __construct(private readonly AddressLibrary $addresses) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Store/Address/Index', [
            'addresses' => $this->addresses->listFor($request->user()->id),
        ]);
    }

    public function store(AddressRequest $request): RedirectResponse
    {
        try {
            $this->addresses->create($request->user()->id, $request->validated());
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return back()->with('status', '배송지를 저장했습니다.');
    }

    public function update(AddressRequest $request, int $address): RedirectResponse
    {
        try {
            $this->addresses->update($request->user()->id, $address, $request->validated());
        } catch (DomainRuleException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return back()->with('status', '배송지를 수정했습니다.');
    }

    public function destroy(Request $request, int $address): RedirectResponse
    {
        try {
            $this->addresses->delete($request->user()->id, $address);
        } catch (DomainRuleException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return back()->with('status', '배송지를 삭제했습니다.');
    }

    public function setDefault(Request $request, int $address): RedirectResponse
    {
        try {
            $this->addresses->setDefault($request->user()->id, $address);
        } catch (DomainRuleException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return back()->with('status', '기본 배송지로 설정했습니다.');
    }
}
