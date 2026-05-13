@extends('layouts.app')

@section('title', 'Add New User')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-12">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Add New User</h6>
                            <a href="{{ route('users.index') }}" class="btn btn-sm btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </div>
                        <div class="card-body">
                            @if (session('success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"
                                        aria-label="Close"></button>
                                </div>
                            @endif

                            @if (session('error'))
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    {{ session('error') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"
                                        aria-label="Close"></button>
                                </div>
                            @endif


                            <form action="{{ route('users.store') }}" id="userForm" method="POST"
                                enctype="multipart/form-data">
                                @csrf

                                {{-- Profile Image Preview --}}
                                <div class="mb-4 text-end me-2">
                                    <label for="profile" class="d-inline-block">
                                        <img id="profilePreview" src="{{ asset('assets/img/default.jpg') }}"
                                            alt="Profile Preview" class="rounded shadow "
                                            style="width: 200px; height: 150px; object-fit: contain; cursor: pointer;">
                                    </label>
                                    <input type="file" name="profile_image" id="profile" accept="image/*"
                                        class="d-none">
                                    @error('profile')
                                        <small class="text-danger d-block mt-2">{{ $message }}</small>
                                    @enderror
                                </div>


                                {{-- Name --}}
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name</label>
                                    <input type="text" name="name" id="name" class="form-control"
                                        value="{{ old('name') }}">
                                    @error('name')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Email --}}
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" name="email" id="email" class="form-control"
                                        value="{{ old('email') }}">
                                    @error('email')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Date of Birth --}}
                                <div class="mb-3">
                                    <label for="dob" class="form-label">Date of Birth</label>
                                    <input type="date" name="dob" id="dob" class="form-control"
                                        value="{{ old('dob') }}">
                                    @error('dob')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Country --}}
                                <div class="mb-3">
                                    <label for="country" class="form-label">City</label>
                                    <select name="city" id="city" class="form-select select2">
                                        <option value="">Select city</option>
                                        @foreach ($cities as $city)
                                            <option value="{{ $city->id }}"
                                                {{ old('city') == $city->id ? 'selected' : '' }}>{{ $city->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('city')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Phone Number --}}
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="text" name="phone" id="phone" class="form-control"
                                        placeholder="+966 512 345 6789" value="{{ old('phone') }}">
                                    @error('phone')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Address --}}
                                <div class="mb-3">
                                    <label for="address" class="form-label">Complete Address</label>
                                    <textarea name="address" id="address" class="form-control" rows="2">{{ old('address') }}</textarea>
                                    @error('address')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Submit --}}
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="bi bi-save me-1"></i> Save
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    {{-- Image preview script --}}
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Profile image preview
                document.getElementById('profile').addEventListener('change', function(e) {
                    const [file] = this.files;
                    if (file) {
                        document.getElementById('profilePreview').src = URL.createObjectURL(file);
                    }
                });

                // Form validation
                document.getElementById('userForm').addEventListener('submit', function(event) {
                    console.log('Form submit event triggered'); // Debugging

                    let isValid = true;
                    let errors = [];

                    // Name
                    const name = document.getElementById('name').value;
                    if (!name) {
                        errors.push({
                            field: 'name',
                            message: 'Please enter the full name.'
                        });
                        isValid = false;
                    } else if (name.length > 255) {
                        errors.push({
                            field: 'name',
                            message: 'Name cannot exceed 255 characters.'
                        });
                        isValid = false;
                    }

                    // Email
                    const email = document.getElementById('email').value;
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!email) {
                        errors.push({
                            field: 'email',
                            message: 'Please enter an email address.'
                        });
                        isValid = false;
                    } else if (!emailRegex.test(email)) {
                        errors.push({
                            field: 'email',
                            message: 'Please enter a valid email address.'
                        });
                        isValid = false;
                    } else if (email.length > 255) {
                        errors.push({
                            field: 'email',
                            message: 'Email cannot exceed 255 characters.'
                        });
                        isValid = false;
                    }

                    // Date of Birth
                    const dob = document.getElementById('dob').value;
                    const today = new Date();
                    const minDate = new Date('1900-01-01');
                    const maxDate = new Date();
                    maxDate.setFullYear(today.getFullYear() - 18); // Minimum age 18
                    const dobDate = new Date(dob);
                    if (!dob) {
                        errors.push({
                            field: 'dob',
                            message: 'Please enter a date of birth.'
                        });
                        isValid = false;
                    } else if (dobDate > today) {
                        errors.push({
                            field: 'dob',
                            message: 'Date of birth cannot be in the future.'
                        });
                        isValid = false;
                    } else if (dobDate > maxDate) {
                        errors.push({
                            field: 'dob',
                            message: 'You must be at least 18 years old.'
                        });
                        isValid = false;
                    } else if (dobDate < minDate) {
                        errors.push({
                            field: 'dob',
                            message: 'Date of birth is too far in the past.'
                        });
                        isValid = false;
                    }

                    // City
                    const city = document.getElementById('city').value;
                    if (!city) {
                        errors.push({
                            field: 'city',
                            message: 'Please select a city.'
                        });
                        isValid = false;
                    }

                    // Phone
                    const phone = document.getElementById('phone').value.trim();
                    const phoneRegex = /^[0-9+\-\s()]{7,20}$/; // Allows numbers, +, -, space, and parentheses

                    if (!phone) {
                        errors.push({
                            field: 'phone',
                            message: 'Please enter a phone number.'
                        });
                        isValid = false;
                    } else if (phone.length > 20) {
                        errors.push({
                            field: 'phone',
                            message: 'Phone number cannot exceed 20 characters.'
                        });
                        isValid = false;
                    } else if (!phoneRegex.test(phone)) {
                        errors.push({
                            field: 'phone',
                            message: 'Please enter a valid phone number.'
                        });
                        isValid = false;
                    }


                    // Address
                    const address = document.getElementById('address').value;
                    if (!address) {
                        errors.push({
                            field: 'address',
                            message: 'Please enter an address.'
                        });
                        isValid = false;
                    } else if (address.length > 1000) {
                        errors.push({
                            field: 'address',
                            message: 'Address cannot exceed 1000 characters.'
                        });
                        isValid = false;
                    }

                    // Profile Image
                    const profileImage = document.getElementById('profile').files[0];
                    if (profileImage && !['image/jpeg', 'image/png'].includes(profileImage.type)) {
                        errors.push({
                            field: 'profile',
                            message: 'Please upload a valid image file (JPEG, PNG).'
                        });
                        isValid = false;
                    }
                    if (profileImage && profileImage.size > 8388608) {
                        errors.push({
                            field: 'profile',
                            message: 'Profile image cannot exceed 8MB.'
                        });
                        isValid = false;
                    }

                    // Display errors
                    document.querySelectorAll('.text-danger.client-side').forEach(el => el.remove());
                    errors.forEach(error => {
                        const field = document.getElementById(error.field);
                        const errorElement = document.createElement('small');
                        errorElement.className = 'text-danger client-side';
                        errorElement.textContent = error.message;
                        field.parentElement.appendChild(errorElement);
                    });

                    if (!isValid) {
                        event.preventDefault(); // Prevent form submission
                    }
                });
            });
        </script>
    @endpush
@endsection
