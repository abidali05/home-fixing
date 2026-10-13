@extends('layouts.app')

@section('title', 'Edit Company Account')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0" style="border-radius: 20px;">
                        <div class="card-header bg-transparent pb-0 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 text-dark font-weight-bold" style="font-size: 1.1rem;">Edit Company Account</h6>
                                <p class="text-muted text-xs mb-0">Modify company details and login parameters.</p>
                            </div>
                            <a href="{{ route('companies.index') }}" class="btn btn-sm btn-secondary border-radius-lg px-3">
                                <i class="bi bi-arrow-left me-1"></i> Back
                            </a>
                        </div>
                        <div class="card-body px-4 pt-3 pb-4">
                            <form action="{{ route('companies.update', $company->id) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="mb-3">
                                    <label for="company_name" class="form-label text-dark font-weight-semibold">Company Name</label>
                                    <input type="text" name="company_name" id="company_name" class="form-control" value="{{ old('company_name', $company->company_name) }}" required>
                                    @error('company_name')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="name" class="form-label text-dark font-weight-semibold">Contact Person Name</label>
                                    <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $company->name) }}" required>
                                    @error('name')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label text-dark font-weight-semibold">Email Address</label>
                                    <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $company->email) }}" required>
                                    @error('email')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="phone" class="form-label text-dark font-weight-semibold">Phone Number</label>
                                    <input type="text" name="phone" id="phone" value="{{ old('phone', $company->phone) }}" class="form-control" placeholder="+966512345678" required>
                                    @error('phone')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label text-dark font-weight-semibold">Login Password (Leave blank to keep current)</label>
                                    <input type="password" name="password" id="password" class="form-control" placeholder="Minimum 6 characters">
                                    @error('password')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="address" class="form-label text-dark font-weight-semibold">Office Address</label>
                                    <textarea name="address" id="address" class="form-control" rows="3">{{ old('address', $company->address) }}</textarea>
                                    @error('address')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="status" class="form-label text-dark font-weight-semibold">Account Status</label>
                                    <select name="status" id="status" class="form-select">
                                        <option value="active" {{ old('status', $company->status) == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ old('status', $company->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                    @error('status')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="d-flex justify-content-end mt-4">
                                    <button type="submit" class="btn btn-primary border-radius-lg px-4" style="background-color: #4F2396 !important; border-color: #4F2396 !important;">
                                        <i class="bi bi-save me-1"></i> Update Company Account
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
