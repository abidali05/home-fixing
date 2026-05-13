@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-12">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Edit User</h6>
                            <a href="{{ route('users.index') }}" class="btn btn-sm btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </div>
                        <div class="card-body">

                            <form action="{{ route('users.update', $user->id) }}" method="POST"
                                enctype="multipart/form-data">
                                @csrf
                                @method('POST')

                                {{-- Profile Image Preview --}}
                                <div class="mb-4 text-end me-2">
                                    <label for="profile" class="d-inline-block">
                                        <img id="profilePreview"
                                            src="{{ $user->profile_image ? asset('uploads/profile_images/' . $user->profile_image) : asset('assets/img/default.jpg') }}"
                                            alt="Profile Preview" class="rounded shadow"
                                            style="width: 200px; height: 150px; object-fit: contain; cursor: pointer;">
                                    </label>
                                    <input type="file" name="profile_image" id="profile" accept="image/*"
                                        class="d-none">
                                    @error('profile_image')
                                        <small class="text-danger d-block mt-2">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Name --}}
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name</label>
                                    <input type="text" name="name" id="name" class="form-control"
                                        value="{{ old('name', $user->name) }}" required>
                                    @error('name')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Email --}}
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" name="email" id="email" class="form-control"
                                        value="{{ old('email', $user->email) }}" required>
                                    @error('email')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Date of Birth --}}
                                <div class="mb-3">
                                    <label for="dob" class="form-label">Date of Birth</label>
                                    <input type="date" name="dob" id="dob" class="form-control"
                                        value="{{ old('dob', $user->dob) }}" required>
                                    @error('dob')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- City --}}
                                <div class="mb-3">
                                    <label for="city" class="form-label">City</label>
                                    <select name="city" id="city" class="form-select select2" required>
                                        <option value="">Select city</option>
                                        @foreach ($cities as $city)
                                            <option value="{{ $city->id }}"
                                                {{ old('city', $user->city_id) == $city->id ? 'selected' : '' }}>
                                                {{ $city->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('city')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Phone --}}
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="text" name="phone" id="phone" class="form-control"
                                        value="{{ old('phone', $user->phone) }}" placeholder="+966 512 345 6789" required readonly>
                                    @error('phone')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Address --}}
                                <div class="mb-3">
                                    <label for="address" class="form-label">Complete Address</label>
                                    <textarea name="address" id="address" class="form-control" rows="2" required>{{ old('address', $user->address) }}</textarea>
                                    @error('address')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>


                                {{-- Status --}}
                                <div class="mb-3">
                                    <label for="address" class="form-label">Status</label>
                                    <select name="status" id="status" class="form-select select2" required>
                                        <option value="active" {{ $user->status == 'active' ? 'selected' : '' }}>Active
                                        </option>
                                        <option value="inactive" {{ $user->status == 'inactive' ? 'selected' : '' }}>
                                            Inactive</option>
                                        <option value="suspended" {{ $user->status == 'suspended' ? 'selected' : '' }}>
                                            Suspended</option>
                                    </select>
                                    @error('status')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Submit --}}
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="bi bi-save me-1"></i> Update
                                    </button>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    {{-- Image preview --}}
    @push('scripts')
        <script>
            document.getElementById('profile').addEventListener('change', function(e) {
                const [file] = this.files;
                if (file) {
                    document.getElementById('profilePreview').src = URL.createObjectURL(file);
                }
            });
        </script>
    @endpush
@endsection
