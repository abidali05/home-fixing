@extends('layouts.app')

@section('content')
    <main class="main-content position-relative border-radius-lg">
        @php
            $setting = App\Models\Admin\SystemSettingModel::first();
        @endphp
    <style>

        .maincontainer {
            margin: 0;
            padding: 0.5rem 1rem 1.5rem 1rem;
            border-radius: 16px;
            width: 100%;
        }

        .chat-container {
            height: calc(100vh - 130px);
            min-height: 550px;
            display: flex;
            background: #ffffff;
            font-family: 'Open Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(79, 35, 150, 0.08);
            border: 1px solid rgba(79, 35, 150, 0.12);
        }

        /* Sidebar Styles */
        .chat-sidebar {
            width: 360px;
            background: #ffffff;
            border-right: 1px solid #e9edef;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            background: linear-gradient(135deg, #4F2396 0%, #682eb8 100%);
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-height: 64px;
            color: #ffffff;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-avatar-small {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 8px;
        }

        .header-actions {
            margin-left: auto;
        }

        .search-container {
            padding: 24px 16px;
            background: #f0f2f5;
            border-top-left-radius: 10px;
        }

        .search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-input {
            width: 100%;
            padding: 8px 16px 8px 48px;
            border: none;
            border-radius: 20px;
            background: #ffffff;
            font-size: 14px;
            outline: none;
        }

        .search-icon {
            position: absolute;
            left: 16px;
            color: #8696a0;
            z-index: 1;
        }

        .chat-list-container {
            flex: 1;
            overflow-y: auto;
        }

        .chat-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .chat-item {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f2f5;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
        }

        .chat-item:hover {
            background: #f5f6f6;
        }

        .chat-item.active {
            background: #e9edef;
        }

        .chat-info {
            flex: 1;
            margin-left: 12px;
            min-width: 0;
        }

        .chat-name {
            font-weight: 500;
            color: #111b21;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-preview {
            color: #667781;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-participants {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
        }

        .participant-info {
            display: flex;
            align-items: center;
            margin-right: 12px;
        }

        .participant-name {
            font-size: 11px;
            color: #667781;
            margin-left: 4px;
        }

        .chat-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            margin-left: 8px;
        }

        .chat-time {
            color: #667781;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .chat-badge {
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .badge-direct {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-order {
            background: #e8f5e8;
            color: #2e7d32;
        }

        /* Messages Panel */
        .messages-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f7f9fc;
        }

        .chat-header {
            background: #ffffff;
            padding: 16px 20px;
            border-bottom: 1px solid rgba(79, 35, 150, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-height: 64px;
        }

        .chat-header-info {
            display: flex;
            align-items: center;
            flex: 1;
        }

        .chat-header-participants {
            display: flex;
            align-items: center;
            margin-top: 4px;
        }

        .chat-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            background: none;
            border: none;
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
            color: #54656f;
            transition: background 0.2s;
        }

        .action-btn:hover {
            background: #e9edef;
        }

        .delete-btn:hover {
            background: #fce4ec;
            color: #d32f2f;
        }

        .messages-area {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .welcome-screen {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .welcome-content {
            text-align: center;
            max-width: 400px;
        }

        .welcome-icon {
            font-size: 120px;
            color: #d9d9d9;
            margin-bottom: 20px;
        }

        .message-bubble {
            margin-bottom: 12px;
            display: flex;
            align-items: flex-end;
        }

        .message-bubble.user1 {
            justify-content: flex-start;
        }

        .message-bubble.user2 {
            justify-content: flex-end;
        }

        .message-bubble.admin {
            justify-content: flex-end;
        }

        .message-content {
            max-width: 65%;
            padding: 8px 12px;
            border-radius: 8px;
            position: relative;
            word-wrap: break-word;
        }

        .message-content.user1 {
            background: #ffffff;
            margin-left: 8px;
            box-shadow: 0 1px 0.5px rgba(0, 0, 0, 0.13);
        }

        .message-content.user2 {
            background: #dcf8c6;
            margin-right: 8px;
        }

        .message-content.admin {
            background: #d9fdd3;
            margin-right: 8px;
            border: 1px solid #00a884;
        }

        .message-text {
            font-size: 14px;
            line-height: 1.4;
            color: #111b21;
        }

        .message-time {
            font-size: 11px;
            color: #667781;
            margin-top: 4px;
            text-align: right;
        }

        .message-status {
            margin-left: 4px;
        }

        .sender-info {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
        }

        .sender-name {
            font-size: 12px;
            font-weight: 500;
            color: #00a884;
            margin-left: 8px;
        }

        .admin-label {
            background: #00a884;
            color: white;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 10px;
            margin-left: 8px;
        }

        .blocked-message {
            text-align: center;
            margin: 20px 0;
        }

        .blocked-badge {
            background: red;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .message-input-area {
            background: #f0f2f5;
            padding: 16px 20px;
            border-top: 1px solid #e9edef;
        }

        .input-wrapper {
            display: flex;
            align-items: center;
            background: #ffffff;
            border-radius: 24px;
            padding: 8px 16px;
        }

        .message-input {
            flex: 1;
            border: none;
            outline: none;
            padding: 8px 12px;
            font-size: 14px;
            background: transparent;
        }

        .emoji-btn,
        .attach-btn,
        .send-btn {
            background: none;
            border: none;
            color: #54656f;
            cursor: pointer;
            padding: 8px;
            padding-left: 12px;
            padding-right: 12px;
            border-radius: 50%;
            transition: background 0.2s;
        }

        .emoji-btn:hover,
        .attach-btn:hover,
        .send-btn:hover {
            background: #e9edef;

        }

        .send-btn {
            color: #2DBECE;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .chat-sidebar {
                width: 100%;
                position: absolute;
                z-index: 100;
            }

            .messages-panel {
                width: 100%;
            }
        }

        /* Custom Scrollbar */
        .chat-list-container::-webkit-scrollbar,
        .messages-area::-webkit-scrollbar {
            width: 6px;
        }

        .chat-list-container::-webkit-scrollbar-track,
        .messages-area::-webkit-scrollbar-track {
            background: transparent;
        }

        .chat-list-container::-webkit-scrollbar-thumb,
        .messages-area::-webkit-scrollbar-thumb {
            background: #8696a0;
            border-radius: 3px;
        }

        .chat-list-container::-webkit-scrollbar-thumb:hover,
        .messages-area::-webkit-scrollbar-thumb:hover {
        }
    </style>
    <div class="container-fluid py-3">
        <div class="chat-container">
            <!-- Chat List Sidebar -->
            <div class="chat-sidebar">
                <!-- Search Bar -->
                <div class="search-container">
                    <div class="search-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="searchChats" placeholder="Search or start new chat" class="search-input">
                    </div>
                </div>

                <!-- Chat List -->
                <div class="chat-list-container">
                    <ul class="chat-list" id="chatList">
                        <!-- Chats will be populated here -->
                    </ul>
                </div>
            </div>

            <!-- Messages Panel -->
            <div class="messages-panel">
                <!-- Chat Header -->
                <div class="chat-header" id="chatHeader">
                    <div class="chat-header-info">
                        <div>
                            <h6 class="mb-0" id="chatName">Select a chat to view messages</h6>
                            <small class="text-muted" id="chatStatus">Click on a chat to start messaging</small>
                            <div class="chat-header-participants" id="chatHeaderParticipants" style="display: none;">
                                <!-- Participants will be shown here -->
                            </div>
                        </div>
                    </div>
                    <div class="chat-actions" id="chatActions" style="display: none;">
                        <button class="action-btn d-none" id="softDeleteBtn" title="Archive Chat">
                            <i class="fas fa-archive"></i>
                        </button>
                        <button class="action-btn" id="disableBtn" title="Block Chat">
                            <i class="fas fa-ban"></i>
                        </button>
                        <button class="action-btn delete-btn" id="hardDeleteBtn" title="Delete Permanently">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button class="action-btn d-none">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                </div>

                <!-- Messages Area -->
                <div class="messages-area" id="chatMessages">
                    <div class="welcome-screen">
                        <div class="welcome-content">
                            <i class="fas fa-comments welcome-icon"></i>
                            <h4>Home Fixing Chat</h4>
                            <p class="text-muted">Manage customer conversations and support requests.<br>Monitor all chat
                                activities from this admin panel.</p>
                        </div>
                    </div>
                </div>

                <!-- Message Input (hidden initially) -->
                <div class="message-input-area" id="messageInputArea" style="display: none;">
                    <div class="input-wrapper">
                        {{-- <button class="emoji-btn">
                            <i class="far fa-smile"></i>
                        </button> --}}
                        <input type="text" placeholder="Type a message" class="message-input" id="messageInput">
                        {{-- <button class="attach-btn">
                            <i class="fas fa-paperclip"></i>
                        </button> --}}
                        <button class="send-btn" id="sendBtn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script type="module">
        import {
            initializeApp
        } from "https://www.gstatic.com/firebasejs/9.17.1/firebase-app.js";
        import {
            getFirestore,
            collection,
            query,
            onSnapshot,
            doc,
            updateDoc,
            deleteDoc,
            orderBy,
            addDoc,
            serverTimestamp
        } from "https://www.gstatic.com/firebasejs/9.17.1/firebase-firestore.js";
        import {
            getDocs,
            writeBatch,
            limit
        } from "https://www.gstatic.com/firebasejs/9.17.1/firebase-firestore.js";

        // Firebase Config
        const firebaseConfig = {
            apiKey: "{{ env('FIREBASE_API_KEY') }}",
            authDomain: "{{ env('FIREBASE_AUTH_DOMAIN') }}",
            projectId: "{{ env('FIREBASE_PROJECT_ID') }}",
        };
        const app = initializeApp(firebaseConfig);
        const db = getFirestore(app);

        const chatListEl = document.getElementById("chatList");
        const chatMessagesEl = document.getElementById("chatMessages");
        const chatHeader = document.getElementById("chatHeader");
        const chatActions = document.getElementById("chatActions");
        const chatName = document.getElementById("chatName");
        const chatStatus = document.getElementById("chatStatus");
        const chatHeaderParticipants = document.getElementById("chatHeaderParticipants");
        const messageInputArea = document.getElementById("messageInputArea");
        const messageInput = document.getElementById("messageInput");
        const sendBtn = document.getElementById("sendBtn");
        const searchInput = document.getElementById("searchChats");
        const defaultAvatar =
            "{{ $setting->logo ? asset('uploads/system_settings/' . $setting->logo) : asset('assets/img/logo.png') }}";
        const companyProviderIds = @json(optional(Auth::guard('admin')->user())->is_company ? \App\Models\User::where('company_id', Auth::guard('admin')->user()->id)->pluck('id')->toArray() : null);
        let activeChatId = null;
        let participantsMap = {};
        let allChats = [];
        let currentChatParticipants = [];

        // Search functionality
        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            const chatItems = chatListEl.querySelectorAll('.chat-item');

            chatItems.forEach(item => {
                const name = item.querySelector('.chat-name').textContent.toLowerCase();
                const preview = item.querySelector('.chat-preview').textContent.toLowerCase();

                if (name.includes(searchTerm) || preview.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Fetch chats in real-time
        const q = query(collection(db, "chats"));
        onSnapshot(q, (snapshot) => {
            chatListEl.innerHTML = "";
            allChats = [];

            snapshot.forEach((docSnap) => {
                const chat = docSnap.data();
                const chatId = docSnap.id;
                const details = chat.participantDetails || {};
                
                if (companyProviderIds !== null) {
                    const participantIds = Object.keys(details).map(Number);
                    const hasAssignedProvider = participantIds.some(id => companyProviderIds.includes(id));
                    if (!hasAssignedProvider) {
                        return;
                    }
                }

                participantsMap[chatId] = details;
                allChats.push({
                    id: chatId,
                    ...chat
                });

                // Chat type: Order or Direct
                const isDirect = chat.is_direct === true || chatId.startsWith("direct_");
                const chatType = isDirect ? 'Direct' : 'Order';
                const badgeClass = isDirect ? 'badge-direct' : 'badge-order';

                // Get participants
                const participants = Object.values(details);
                const participantNames = participants.map(p => p.name || 'Unknown').join(' & ');

                // Format last message time
                const lastMessageTime = chat.lastMessageTime ?
                    new Date(chat.lastMessageTime.seconds * 1000).toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit'
                    }) :
                    '';

                // Create participant images HTML
                let participantImagesHtml = '';
                participants.slice(0, 2).forEach(participant => {
                    participantImagesHtml += `
                        <div class="participant-info">
                            <img src="${participant.image || defaultAvatar }" 
                                 class="user-avatar-small" alt="${participant.name}">
                            <span class="participant-name">${participant.name || 'Unknown'}</span>
                        </div>
                    `;
                });

                // Create chat item
                const chatItem = document.createElement('li');
                chatItem.className = 'chat-item';
                chatItem.onclick = () => viewChat(chatId, participants);

                chatItem.innerHTML = `
                    <div style="position: relative;">
                        <img src="${participants[0]?.image || defaultAvatar}" 
                             class="user-avatar" alt="${participantNames}">
                        ${participants.length > 1 ? `<img src="${participants[1]?.image || defaultAvatar }" 
                                                                 style="position: absolute; bottom: -2px; right: -2px; width: 20px; height: 20px; border: 2px solid white;" 
                                                                 class="user-avatar-small" alt="${participants[1]?.name}">` : ''}
                    </div>
                    <div class="chat-info">
                        <div class="chat-name">${participantNames}</div>
                        <div class="chat-participants">
                            ${participantImagesHtml}
                        </div>
                        <div class="chat-preview">${chat.last_message || "No messages yet"}</div>
                    </div>
                    <div class="chat-meta">
                        <div class="chat-time">${lastMessageTime}</div>
                        <span class="chat-badge ${badgeClass}">${chatType}</span>
                    </div>
                `;

                chatListEl.appendChild(chatItem);
            });
        });

        // View chat messages
        window.viewChat = (chatId, participants) => {
            // Remove active class from all chat items
            document.querySelectorAll('.chat-item').forEach(item => item.classList.remove('active'));

            // Add active class to clicked item
            event.target.closest('.chat-item').classList.add('active');

            activeChatId = chatId;
            currentChatParticipants = participants;

            const participantNames = participants.map(p => p.name || 'Unknown').join(' & ');
            chatName.textContent = participantNames;
            chatStatus.textContent = `${participants.length} participant${participants.length > 1 ? 's' : ''}`;

            // Show participants in header
            chatHeaderParticipants.style.display = 'flex';
            chatHeaderParticipants.innerHTML = '';
            participants.forEach(participant => {
                chatHeaderParticipants.innerHTML += `
                    <div class="participant-info">
                        <img src="${participant.image || defaultAvatar}" 
                             class="user-avatar-small" alt="${participant.name}">
                        <span class="participant-name">${participant.name || 'Unknown'}</span>
                    </div>
                `;
            });

            chatActions.style.display = 'flex';
            messageInputArea.style.display = 'block';

            // Hide welcome screen
            const welcomeScreen = chatMessagesEl.querySelector('.welcome-screen');
            if (welcomeScreen) {
                welcomeScreen.style.display = 'none';
            }

            chatMessagesEl.innerHTML = "";
            const msgRef = query(collection(db, "chats", chatId, "messages"), orderBy("timestamp", "asc"));

            onSnapshot(msgRef, (snapshot) => {
                chatMessagesEl.innerHTML = "";

                snapshot.forEach((docSnap) => {
                    const msg = docSnap.data();

                    // If message blocked
                    // if (msg.blocked && msg.sender !== "ADMIN") {
                    //     chatMessagesEl.innerHTML += `
                //         <div class="blocked-message">
                //             <span class="blocked-badge">Message blocked { ${msg.content}  }</span>
                //         </div>
                //     `;
                    //     return;
                    // }

                    const sender = msg.sender;
                    const details = participantsMap[chatId] || {};
                    const senderInfo = details[sender] || {};
                    const isAdmin = sender === "ADMIN";

                    // Determine message alignment based on sender
                    let bubbleClass, contentClass;
                    if (isAdmin) {
                        bubbleClass = 'admin';
                        contentClass = 'admin';
                    } else {
                        // Assign user1 or user2 based on participant order
                        const participantIds = Object.keys(details);
                        const senderIndex = participantIds.indexOf(sender);
                        bubbleClass = senderIndex === 0 ? 'user1' : 'user2';
                        contentClass = senderIndex === 0 ? 'user1' : 'user2';
                    }

                    const messageTime = msg.timestamp?.seconds ?
                        new Date(msg.timestamp.seconds * 1000).toLocaleTimeString([], {
                            hour: '2-digit',
                            minute: '2-digit'
                        }) :
                        '';

                    chatMessagesEl.innerHTML += `
                        <div class="message-bubble ${bubbleClass}">
                            <div class="message-content ${contentClass}">
                                <div class="sender-info">
                                    <img src="${senderInfo.image || defaultAvatar}" 
                                         class="user-avatar-small" alt="${senderInfo.name}">
                                    <span class="sender-name">${senderInfo.name || isAdmin ? '' : 'Unknown'}</span>
                                    ${isAdmin ? '<span class="admin-label">ADMIN</span>' : ''}
                                </div>
                                <div class="message-text">${msg.content}  ${msg.blocked && msg.sender !== "ADMIN"  ? '<small class="blocked-badge" style="font-size: 9px;">Message blocked</small>' : ''}</div>
                                <div class="message-time">
                                    ${messageTime}
                                </div>
                            </div>
                        </div>
                    `;
                });

                chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
            });
        };

        // Send message functionality
        const sendMessage = async () => {
            if (!activeChatId || !messageInput.value.trim()) return;

            const messageText = messageInput.value.trim();
            messageInput.value = '';

            try {
                await addDoc(collection(db, "chats", activeChatId, "messages"), {
                    content: messageText,
                    sender: "ADMIN",
                    timestamp: serverTimestamp(),
                    blocked: false
                });

                // Update last message in chat
                await updateDoc(doc(db, "chats", activeChatId), {
                    last_message: messageText,
                    lastMessageTime: serverTimestamp()
                });

                showNotification("Message sent successfully!", "success");
            } catch (error) {
                console.error("Error sending message:", error);
                showNotification("Error sending message", "error");
            }
        };

        // Send message on Enter key or button click
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });

        sendBtn.addEventListener('click', sendMessage);

        // Actions - Fixed event listeners
        document.getElementById("softDeleteBtn").addEventListener("click", async (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (activeChatId && confirm("Archive this chat?")) {
                try {
                    await updateDoc(doc(db, "chats", activeChatId), {
                        status: 'closed'
                    });
                    showNotification("Chat archived successfully!", "success");
                } catch (error) {
                    console.error("Error archiving chat:", error);
                    showNotification("Error archiving chat", "error");
                }
            }
        });

        document.getElementById("hardDeleteBtn").addEventListener("click", async (e) => {
            e.preventDefault();
            e.stopPropagation();

            if (activeChatId && confirm(
                    "Are you sure you want to delete this chat permanently? This action cannot be undone.")) {
                try {
                    // 1. Delete all messages in batches
                    const messagesRef = collection(db, "chats", activeChatId, "messages");
                    const messagesSnap = await getDocs(messagesRef);

                    let batch = writeBatch(db);
                    let counter = 0;

                    messagesSnap.forEach((msgDoc) => {
                        batch.delete(msgDoc.ref);
                        counter++;

                        // Commit every 500 docs (Firestore batch limit)
                        if (counter === 500) {
                            batch.commit();
                            batch = writeBatch(db);
                            counter = 0;
                        }
                    });

                    // Commit remaining docs
                    if (counter > 0) {
                        await batch.commit();
                    }

                    // 2. Delete the chat document itself
                    await deleteDoc(doc(db, "chats", activeChatId));

                    // 3. Reset UI
                    chatMessagesEl.innerHTML = `
                <div class="welcome-screen">
                    <div class="welcome-content">
                        <i class="fas fa-comments welcome-icon"></i>
                        <h4>Chat Deleted</h4>
                        <p class="text-muted">This chat has been permanently deleted.</p>
                    </div>
                </div>
            `;

                    chatName.textContent = "Select a chat to view messages";
                    chatStatus.textContent = "Click on a chat to start messaging";
                    chatHeaderParticipants.style.display = 'none';
                    chatActions.style.display = 'none';
                    messageInputArea.style.display = 'none';
                    activeChatId = null;

                    showNotification("Chat deleted permanently!", "success");
                } catch (error) {
                    console.error("Error deleting chat:", error);
                    showNotification("Error deleting chat", "error");
                }
            }
        });

        document.getElementById("disableBtn").addEventListener("click", async (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (activeChatId && confirm("Disable this chat? Users won't be able to send messages.")) {
                try {
                    await updateDoc(doc(db, "chats", activeChatId), {
                        status: "closed"
                    });
                    const lastMsgQuery = query(
                        collection(db, "chats", activeChatId, "messages"),
                        orderBy("timestamp", "desc"),
                        limit(1)
                    );

                    const lastMsgSnap = await getDocs(lastMsgQuery);

                    if (!lastMsgSnap.empty) {
                        const lastMsgDoc = lastMsgSnap.docs[0];
                        await updateDoc(lastMsgDoc.ref, {
                            blocked: true
                        });
                    }

                    showNotification("Chat disabled successfully!", "success");
                } catch (error) {
                    console.error("Error disabling chat:", error);
                    showNotification("Error disabling chat", "error");
                }
            }
        });

        // Notification function
        function showNotification(message, type) {
            // Create notification element
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                background: ${type === 'success' ? '#4caf50' : '#f44336'};
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                z-index: 10000;
                font-size: 14px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                transform: translateX(400px);
                transition: transform 0.3s ease;
            `;
            notification.textContent = message;

            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => notification.style.transform = 'translateX(0)', 100);

            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.transform = 'translateX(400px)';
                setTimeout(() => {
                    if (document.body.contains(notification)) {
                        document.body.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }
    </script>
@endpush
