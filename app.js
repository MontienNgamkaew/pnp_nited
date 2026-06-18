// ==========================================
// Global State & Configurations
// ==========================================
let currentUser = null;
let currentRole = null;
let signaturePad = null;
let ctx = null;
let isDrawing = false;
let hasSigned = false;
let activeCriteria = [];

// Departments list of Phanom Phrai Industrial and Community Education College
const PHANOMPHRAI_DEPTS = [
    "แผนกวิชาช่างยนต์",
    "แผนกวิชาช่างไฟฟ้ากำลัง",
    "แผนกวิชาช่างอิเล็กทรอนิกส์",
    "แผนกวิชาช่างกลโรงงาน",
    "แผนกวิชาการบัญชี",
    "แผนกวิชาคอมพิวเตอร์ธุรกิจ",
    "แผนกวิชาเทคโนโลยีธุรกิจดิจิทัล"
];

// ==========================================
// Initialization on DOM Load
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
    checkSession();
    setupLoginHandler();
    setupNavigationTabs();
    setupSignaturePad();
    setupDynamicStudentsInput();
    setupPhotoUploadPreviews();
    setupFormSubmissions();
    setupMobileSidebar();
});

// ==========================================
// Authentication & Session Management
// ==========================================
function checkSession() {
    fetch('api_supervision.php?action=get_session')
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success' && res.session) {
                currentUser = res.session;
                currentRole = res.session.role;
                showAppLayout();
            } else {
                showLoginLayout();
            }
        })
        .catch(err => {
            console.error('Session validation error:', err);
            showLoginLayout();
        });
}

function showLoginLayout() {
    document.getElementById('login-screen').style.display = 'flex';
    document.getElementById('app-container').style.display = 'none';
}

function showAppLayout() {
    document.getElementById('login-screen').style.display = 'none';
    document.getElementById('app-container').style.display = 'flex';
    
    // Fill profile info (No prefix prefix referenced)
    document.getElementById('user-fullname').textContent = currentUser.fullname;
    document.getElementById('user-role').textContent = currentRole === 'admin' ? 'ผู้ดูแลระบบ' : 'ครูนิเทศก์';
    document.getElementById('user-role').className = `role-badge ${currentRole}`;
    document.getElementById('current-dept-badge').textContent = currentUser.department;
    document.getElementById('form-supervisor-real').value = currentUser.fullname;

    // Display appropriate menu
    const menuTeacher = document.getElementById('menu-teacher');
    const menuAdmin = document.getElementById('menu-admin');

    if (currentRole === 'admin') {
        menuTeacher.style.display = 'none';
        menuAdmin.style.display = 'block';
        switchTab('admin-dashboard');
    } else {
        menuTeacher.style.display = 'block';
        menuAdmin.style.display = 'none';
        switchTab('teacher-dashboard');
    }
}

function setupLoginHandler() {
    const loginForm = document.getElementById('login-form');
    if (!loginForm) return;

    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const nid = document.getElementById('login-nid').value.replace(/[- ]/g, '').trim();
        const pwd = document.getElementById('login-password').value;

        if (nid !== 'admin' && (nid.length !== 13 || isNaN(nid))) {
            showToast('กรุณากรอกเลขบัตรประชาชน 13 หลักให้ถูกต้อง', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('national_id', nid);
        formData.append('password', pwd);

        fetch('login.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                showToast('เข้าสู่ระบบสำเร็จ ยินดีต้อนรับ!', 'success');
                checkSession();
                loginForm.reset();
            } else {
                showToast(res.message || 'รหัสผ่านไม่ถูกต้อง', 'error');
            }
        })
        .catch(err => {
            console.error('Login error:', err);
            showToast('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', 'error');
        });
    });

    const logoutBtn = document.getElementById('btn-logout');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => {
            if (confirm('คุณต้องการออกจากระบบใช่หรือไม่?')) {
                location.href = 'logout.php';
            }
        });
    }
}

// ==========================================
// Navigation & Screen switching
// ==========================================
function setupNavigationTabs() {
    const tabLinks = document.querySelectorAll('.sidebar-nav ul li');
    
    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const tabName = this.getAttribute('data-tab');
            switchTab(tabName);
        });
    });

    document.querySelectorAll('.btn-goto-tab').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = this.getAttribute('data-target');
            switchTab(target);
        });
    });
}

function switchTab(tabName) {
    document.querySelectorAll('.sidebar-nav ul li').forEach(l => l.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(s => s.classList.remove('active'));

    const activeLink = document.querySelector(`.sidebar-nav ul li[data-tab="${tabName}"]`);
    if (activeLink) activeLink.classList.add('active');

    const targetSection = document.getElementById(`tab-${tabName}`);
    if (targetSection) targetSection.classList.add('active');

    const titles = {
        'teacher-dashboard': 'ภาพรวมระบบนิเทศ',
        'new-report': 'บันทึกรายงานการนิเทศงาน',
        'teacher-history': 'แฟ้มประวัติรายงานการนิเทศ',
        'admin-dashboard': 'แผงควบคุมหลักผู้ดูแลระบบ',
        'admin-teachers': 'จัดการรายชื่อครูนิเทศก์',
        'admin-criteria': 'จัดการหัวข้อการประเมิน'
    };
    document.getElementById('page-title').textContent = titles[tabName] || 'ระบบรายงานการนิเทศ';

    if (tabName === 'teacher-dashboard') {
        loadTeacherDashboard();
    } else if (tabName === 'teacher-history') {
        loadTeacherHistoryList();
    } else if (tabName === 'new-report') {
        resetSupervisionForm();
    } else if (tabName === 'admin-dashboard') {
        loadAdminDashboard();
    } else if (tabName === 'admin-teachers') {
        loadAdminTeachersList();
    } else if (tabName === 'admin-criteria') {
        loadAdminCriteriaList();
    }

    document.querySelector('.sidebar').classList.remove('active');
}

// ==========================================
// Part 2: Dynamic Student Rows Management
// ==========================================
let studentRowCount = 0;

function setupDynamicStudentsInput() {
    const addBtn = document.getElementById('btn-add-student-row');
    if (addBtn) {
        addBtn.addEventListener('click', () => {
            addStudentInputRow();
        });
    }
}

function addStudentInputRow(name = '', level = 'ปวช.', year = 1, major = '') {
    const container = document.getElementById('students-dynamic-rows');
    if (!container) return;

    studentRowCount++;
    const rowId = `student-row-${studentRowCount}`;

    if (!major && currentUser && currentUser.department) {
        const matched = PHANOMPHRAI_DEPTS.find(d => d === currentUser.department);
        major = matched ? matched : PHANOMPHRAI_DEPTS[0];
    } else if (!major) {
        major = PHANOMPHRAI_DEPTS[0];
    }

    const deptOptionsHtml = PHANOMPHRAI_DEPTS.map(d => {
        return `<option value="${d}" ${major === d ? 'selected' : ''}>${d}</option>`;
    }).join('');

    const rowHtml = `
        <div class="student-row-grid animate-fade-in" id="${rowId}">
            <div class="form-group">
                <label>ชื่อ - นามสกุลผู้เรียน <span class="required">*</span></label>
                <input type="text" name="student_names[]" value="${name}" required placeholder="ชื่อ นามสกุล">
            </div>
            <div class="form-group">
                <label>ระดับชั้น <span class="required">*</span></label>
                <select name="student_levels[]" required>
                    <option value="ปวช." ${level === 'ปวช.' ? 'selected' : ''}>ปวช.</option>
                    <option value="ปวส." ${level === 'ปวส.' ? 'selected' : ''}>ปวส.</option>
                </select>
            </div>
            <div class="form-group">
                <label>ชั้นปีที่ <span class="required">*</span></label>
                <select name="student_years[]" required>
                    <option value="1" ${year === 1 ? 'selected' : ''}>1</option>
                    <option value="2" ${year === 2 ? 'selected' : ''}>2</option>
                    <option value="3" ${year === 3 ? 'selected' : ''}>3</option>
                    <option value="4" ${year === 4 ? 'selected' : ''}>4</option>
                </select>
            </div>
            <div class="form-group">
                <label>แผนกวิชา <span class="required">*</span></label>
                <select name="student_majors[]" required>
                    ${deptOptionsHtml}
                </select>
            </div>
            <div>
                <button type="button" class="btn-remove-row" onclick="removeStudentInputRow('${rowId}')" title="ลบผู้เรียนคนนี้">
                    <i class="fa-solid fa-circle-minus"></i>
                </button>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', rowHtml);
}

window.removeStudentInputRow = function(rowId) {
    const row = document.getElementById(rowId);
    const container = document.getElementById('students-dynamic-rows');
    
    if (container.children.length > 1) {
        row.remove();
    } else {
        showToast('ต้องมีข้อมูลผู้เรียนอย่างน้อย 1 คน', 'warning');
    }
};

// ==========================================
// Part 3: Dynamic Evaluation Matrix Loader & Calculator
// ==========================================
function loadActiveCriteriaForm() {
    const tbody = document.querySelector('.evaluation-table tbody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="3" class="center">กำลังโหลดหัวข้อประเมิน...</td></tr>';

    fetch('api_supervision.php?action=list_criteria')
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                activeCriteria = res.data;
                renderCriteriaFormRows();
            } else {
                tbody.innerHTML = '<tr><td colspan="3" class="center text-danger">เกิดข้อผิดพลาดในการโหลดหัวข้อประเมิน</td></tr>';
            }
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="3" class="center text-danger">ไม่สามารถเชื่อมต่อระบบหัวข้อประเมินได้</td></tr>';
        });
}

function renderCriteriaFormRows() {
    const tbody = document.querySelector('.evaluation-table tbody');
    if (!tbody) return;

    if (activeCriteria.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="center text-warning">ไม่มีหัวข้อประเมินในระบบขณะนี้ กรุณาติดต่อแอดมิน</td></tr>';
        return;
    }

    tbody.innerHTML = activeCriteria.map((c, idx) => {
        return `
            <tr>
                <td class="center">${idx + 1}</td>
                <td>${c.title}</td>
                <td class="center radios-cell">
                    <label class="radio-lbl"><input type="radio" name="scores[${c.id}]" value="4" required> 4</label>
                    <label class="radio-lbl"><input type="radio" name="scores[${c.id}]" value="3"> 3</label>
                    <label class="radio-lbl"><input type="radio" name="scores[${c.id}]" value="2"> 2</label>
                    <label class="radio-lbl"><input type="radio" name="scores[${c.id}]" value="1"> 1</label>
                </td>
            </tr>
        `;
    }).join('');

    const radios = tbody.querySelectorAll('input[type="radio"]');
    radios.forEach(radio => {
        radio.addEventListener('change', calculateLiveEvaluationResult);
    });
}

function calculateLiveEvaluationResult() {
    if (activeCriteria.length === 0) return;

    let scoreSum = 0;
    let selectedCount = 0;

    activeCriteria.forEach(c => {
        const checkedRadio = document.querySelector(`input[name="scores[${c.id}]"]:checked`);
        if (checkedRadio) {
            scoreSum += parseInt(checkedRadio.value);
            selectedCount++;
        }
    });

    const avgEl = document.getElementById('live-avg-score');
    const resultEl = document.getElementById('live-eval-result');

    if (selectedCount === activeCriteria.length) {
        const avg = (scoreSum / activeCriteria.length).toFixed(2);
        avgEl.textContent = avg;
        
        let resultText = "ควรปรับปรุง";
        let colorClass = "text-danger";

        if (avg >= 3.50) {
            resultText = "ดีมาก";
            colorClass = "text-success";
        } else if (avg >= 2.50) {
            resultText = "ดี";
            colorClass = "text-info";
        } else if (avg >= 1.50) {
            resultText = "พอใช้";
            colorClass = "text-warning";
        }

        resultEl.textContent = resultText;
        resultEl.className = colorClass;
    } else {
        avgEl.textContent = "0.00";
        resultEl.textContent = `ประเมินแล้ว ${selectedCount}/${activeCriteria.length} ข้อ`;
        resultEl.className = "text-muted";
    }
}

// ==========================================
// Part 4: Photo File Input Previews
// ==========================================
function setupPhotoUploadPreviews() {
    for (let i = 1; i <= 4; i++) {
        const input = document.getElementById(`photo-file-${i}`);
        const preview = document.getElementById(`preview-${i}`);

        if (input && preview) {
            input.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = (e) => {
                        preview.innerHTML = `
                            <img src="${e.target.result}" alt="Preview image ${i}">
                            <button type="button" class="btn-remove-img" onclick="clearPhotoInput(${i})" title="ลบรูปภาพ">&times;</button>
                        `;
                        preview.style.display = 'block';
                    };
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
    }
}

window.clearPhotoInput = function(slot) {
    const input = document.getElementById(`photo-file-${slot}`);
    const preview = document.getElementById(`preview-${slot}`);
    if (input) input.value = '';
    if (preview) {
        preview.innerHTML = '';
        preview.style.display = 'none';
    }
};

// ==========================================
// Part 5: Signature Drawing Pad Canvas
// ==========================================
function setupSignaturePad() {
    const canvas = document.getElementById('signature-pad');
    if (!canvas) return;

    ctx = canvas.getContext('2d');
    ctx.strokeStyle = '#0f2d59';
    ctx.lineWidth = 3;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    document.getElementById('btn-clear-sig').addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasSigned = false;
    });

    canvas.addEventListener('mousedown', startSigDrawing);
    canvas.addEventListener('mousemove', sigDrawing);
    canvas.addEventListener('mouseup', stopSigDrawing);
    canvas.addEventListener('mouseout', stopSigDrawing);

    canvas.addEventListener('touchstart', (e) => {
        e.preventDefault();
        const touch = e.touches[0];
        const rect = canvas.getBoundingClientRect();
        ctx.beginPath();
        ctx.moveTo(touch.clientX - rect.left, touch.clientY - rect.top);
        isDrawing = true;
    });

    canvas.addEventListener('touchmove', (e) => {
        e.preventDefault();
        if (!isDrawing) return;
        const touch = e.touches[0];
        const rect = canvas.getBoundingClientRect();
        ctx.lineTo(touch.clientX - rect.left, touch.clientY - rect.top);
        ctx.stroke();
        hasSigned = true;
    });

    canvas.addEventListener('touchend', () => isDrawing = false);
}

function startSigDrawing(e) {
    isDrawing = true;
    const rect = e.target.getBoundingClientRect();
    ctx.beginPath();
    ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
}

function sigDrawing(e) {
    if (!isDrawing) return;
    const rect = e.target.getBoundingClientRect();
    ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
    ctx.stroke();
    hasSigned = true;
}

function stopSigDrawing() {
    isDrawing = false;
}

function resizeSignatureCanvas() {
    const canvas = document.getElementById('signature-pad');
    if (!canvas) return;
    const container = canvas.parentElement;
    
    const tempImg = canvas.toDataURL();
    canvas.width = Math.min(450, container.clientWidth - 16);
    
    ctx.strokeStyle = '#0f2d59';
    ctx.lineWidth = 3;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    if (hasSigned) {
        const img = new Image();
        img.onload = () => ctx.drawImage(img, 0, 0);
        img.src = tempImg;
    }
}

// ==========================================
// Form Submissions
// ==========================================
function setupFormSubmissions() {
    // 1. Submit New Supervision Report
    const formSupervision = document.getElementById('form-supervision-real');
    if (formSupervision) {
        formSupervision.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate student list
            const studentNamesInput = document.querySelectorAll('input[name="student_names[]"]');
            let studentOk = false;
            studentNamesInput.forEach(inp => {
                if (inp.value.trim() !== '') studentOk = true;
            });

            if (!studentOk) {
                showToast('กรุณากรอกข้อมูลผู้เรียนที่เข้ารับนิเทศอย่างน้อย 1 คน', 'error');
                return;
            }

            // Validate evaluation scores count matching loaded active criteria list length
            let scoresFilled = true;
            for (let i = 0; i < activeCriteria.length; i++) {
                const c_id = activeCriteria[i].id;
                if (!document.querySelector(`input[name="scores[${c_id}]"]:checked`)) {
                    scoresFilled = false;
                    break;
                }
            }

            if (!scoresFilled) {
                showToast('กรุณาประเมินรายการประเมินผลการเรียนรู้ให้ครบถ้วนทุกข้อ', 'error');
                return;
            }

            // Validate signature drawing
            if (!hasSigned) {
                showToast('กรุณาลงลายมือชื่อผู้ทำการนิเทศ', 'error');
                return;
            }

            const signatureData = document.getElementById('signature-pad').toDataURL();

            // Setup FormData
            const formData = new FormData(this);
            formData.append('signature', signatureData);

            showToast('กำลังส่งข้อมูลรายงาน...', 'info');
            
            fetch('api_supervision.php?action=add', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    showToast('บันทึกข้อมูลรายงานการนิเทศงานเสร็จสมบูรณ์!', 'success');
                    switchTab('teacher-dashboard');
                } else {
                    showToast(res.message || 'เกิดข้อผิดพลาดในการบันทึกข้อมูล', 'error');
                }
            })
            .catch(err => {
                console.error('Supervision save error:', err);
                showToast('ไม่สามารถเชื่อมต่อฐานข้อมูลปลายทางได้', 'error');
            });
        });
    }

    // 2. Admin: Add teacher (Removed prefix variable capture)
    const formTeacherAdd = document.getElementById('form-teacher-add');
    if (formTeacherAdd) {
        formTeacherAdd.addEventListener('submit', function(e) {
            e.preventDefault();

            const nid = document.getElementById('t-nid').value.trim();
            const password = document.getElementById('t-password').value;
            const name = document.getElementById('t-name').value.trim();
            const lastname = document.getElementById('t-lastname').value.trim();
            const department = document.getElementById('t-dept').value;

            if (nid.length !== 13 || isNaN(nid)) {
                showToast('เลขบัตรประชาชนต้องประกอบด้วยตัวเลข 13 หลัก', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('national_id', nid);
            formData.append('password', password);
            formData.append('name', name);
            formData.append('lastname', lastname);
            formData.append('department', department);
            formData.append('role', 'teacher');

            fetch('admin_api.php?action=add_teacher', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    showToast(res.message, 'success');
                    formTeacherAdd.reset();
                    loadAdminTeachersList();
                } else {
                    showToast(res.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('เซิร์ฟเวอร์ขัดข้องระหว่างบันทึกรายชื่อ', 'error');
            });
        });
    }

    // 3. Admin: CSV Import
    const formCsv = document.getElementById('form-csv-import');
    if (formCsv) {
        formCsv.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            showToast('กำลังวิเคราะห์และนำเข้าไฟล์ CSV...', 'info');

            fetch('admin_api.php?action=import_csv', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    showToast(res.message, 'success');
                    formCsv.reset();
                    loadAdminTeachersList();
                } else {
                    showToast(res.message || 'โครงสร้างไฟล์ CSV ผิดพลาด', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('เกิดข้อผิดพลาดในการโหลดไฟล์ CSV', 'error');
            });
        });
    }

    // 4. Admin: Add Evaluation Criteria
    const formCriteriaAdd = document.getElementById('form-criteria-add');
    if (formCriteriaAdd) {
        formCriteriaAdd.addEventListener('submit', function(e) {
            e.preventDefault();

            const title = document.getElementById('crit-title').value.trim();
            if (!title) return;

            const formData = new FormData();
            formData.append('title', title);

            fetch('admin_api.php?action=add_criteria', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    showToast(res.message, 'success');
                    formCriteriaAdd.reset();
                    loadAdminCriteriaList();
                } else {
                    showToast(res.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('เกิดข้อผิดพลาดบนเซิร์ฟเวอร์', 'error');
            });
        });
    }
}

// ==========================================
// View Resets
// ==========================================
function resetSupervisionForm() {
    const form = document.getElementById('form-supervision-real');
    if (form) {
        form.reset();
        
        const dynamicDiv = document.getElementById('students-dynamic-rows');
        if (dynamicDiv) {
            dynamicDiv.innerHTML = '';
            addStudentInputRow();
        }

        document.getElementById('live-avg-score').textContent = '0.00';
        document.getElementById('live-eval-result').textContent = '-';
        document.getElementById('live-eval-result').className = 'text-muted';

        loadActiveCriteriaForm();
        clearCanvasDrawing();
        
        for (let i = 1; i <= 4; i++) {
            clearPhotoInput(i);
        }

        if (currentUser) {
            document.getElementById('form-supervisor-real').value = currentUser.fullname;
        }

        setTimeout(() => {
            resizeSignatureCanvas();
        }, 150);
    }
}

function clearCanvasDrawing() {
    const canvas = document.getElementById('signature-pad');
    if (canvas && ctx) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasSigned = false;
    }
}

// ==========================================
// Teacher Views Loaders
// ==========================================
function loadTeacherDashboard() {
    fetch('api_supervision.php?action=list')
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                const data = res.data;
                document.getElementById('t-stat-my-reports').textContent = data.length;
                
                let totalStudentCount = 0;
                data.forEach(rep => {
                    totalStudentCount += (rep.students ? rep.students.length : 0);
                });
                document.getElementById('t-stat-students').textContent = totalStudentCount;

                const tbody = document.querySelector('#teacher-recent-table tbody');
                const recentList = data.slice(0, 5);

                if (recentList.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:var(--text-muted);">คุณยังไม่มีประวัติการบันทึกการนิเทศในฐานข้อมูล</td></tr>`;
                    return;
                }

                tbody.innerHTML = recentList.map(r => {
                    return `
                        <tr>
                            <td><strong>${formatThaiDate(r.supervision_date)}</strong></td>
                            <td>ภาคเรียนที่ ${r.semester} / ${r.academic_year}</td>
                            <td>${r.company_name}</td>
                            <td>${r.students ? r.students.length : 0} คน</td>
                            <td><span class="score-indicator"><i class="fa-solid fa-star"></i> ${r.score_avg}</span></td>
                            <td><span class="status-badge ${getEvalClass(r.eval_result)}">${r.eval_result}</span></td>
                            <td>
                                <button class="btn btn-outline" style="padding: 4px 8px; font-size:11px;" onclick="openDetailModal('${r.id}')">
                                    <i class="fa-solid fa-eye"></i> ดูผลลัพธ์
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
            }
        });
}

function loadTeacherHistoryList() {
    const listDiv = document.getElementById('teacher-reports-list');
    if (!listDiv) return;

    fetch('api_supervision.php?action=list')
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                const data = res.data;

                if (data.length === 0) {
                    listDiv.innerHTML = `<div style="grid-column: 1/-1; text-align: center; color: var(--text-muted); padding: 40px;">คุณยังไม่มีประวัติการนิเทศนักศึกษา</div>`;
                    return;
                }

                renderHistoryCards(data);

                const searchInp = document.getElementById('search-teacher-history');
                if (searchInp) {
                    const newSearchInp = searchInp.cloneNode(true);
                    searchInp.replaceWith(newSearchInp);
                    newSearchInp.addEventListener('input', function() {
                        const q = this.value.trim().toLowerCase();
                        const filtered = data.filter(r => {
                            const matchCompany = r.company_name.toLowerCase().includes(q);
                            const matchStudent = r.students && r.students.some(s => s.student_name.toLowerCase().includes(q));
                            return matchCompany || matchStudent;
                        });
                        renderHistoryCards(filtered);
                    });
                }
            }
        });
}

function renderHistoryCards(reportList) {
    const listDiv = document.getElementById('teacher-reports-list');
    
    listDiv.innerHTML = reportList.map(r => {
        const studentNamesStr = r.students ? r.students.map(s => `${s.student_name} (${s.level} ${s.major})`).join(', ') : '';
        return `
            <div class="report-card">
                <div class="report-card-header">
                    <span class="date"><i class="fa-regular fa-calendar"></i> ${formatThaiDate(r.supervision_date)}</span>
                    <span class="status-badge ${getEvalClass(r.eval_result)}">${r.eval_result}</span>
                </div>
                <div class="report-card-body">
                    <h4>${r.company_name}</h4>
                    <p class="company"><i class="fa-solid fa-user-graduate"></i> ${studentNamesStr}</p>
                    
                    <div class="report-scores-summary" style="display:flex; justify-content:space-between;">
                        <span>คะแนนเฉลี่ย: <strong>${r.score_avg}</strong> / 4.00</span>
                        <span>ภาคเรียน: <strong>${r.semester}/${r.academic_year}</strong></span>
                    </div>
                </div>
                <div class="report-card-footer">
                    <span>ผู้นิเทศ: ${r.teacher_name} ${r.teacher_lastname}</span>
                    <button class="btn btn-outline" style="padding: 4px 8px; font-size:11px;" onclick="openDetailModal('${r.id}')">
                        ดูรายละเอียด <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

// ==========================================
// Admin Views Loaders
// ==========================================
function loadAdminDashboard() {
    fetch('admin_api.php?action=dashboard_stats')
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                const metrics = res.data;

                document.getElementById('a-stat-reports').textContent = metrics.total_reports;
                document.getElementById('a-stat-teachers').textContent = metrics.total_teachers;
                document.getElementById('a-stat-students').textContent = metrics.total_students;

                const deptChartDiv = document.getElementById('admin-dept-chart');
                if (metrics.dept_stats && metrics.dept_stats.length > 0) {
                    const maxCount = Math.max(...metrics.dept_stats.map(d => d.count), 1);
                    deptChartDiv.innerHTML = metrics.dept_stats.map(d => {
                        const pct = Math.round((d.count / maxCount) * 100);
                        return `
                            <div class="progress-item">
                                <div class="progress-label">
                                    <span>${d.department}</span>
                                    <span class="progress-val">${d.count} ครั้ง</span>
                                </div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar bg-blue" style="width: ${pct}%"></div>
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                    deptChartDiv.innerHTML = '<p class="text-muted text-center">ยังไม่มีข้อมูลรายงานของแผนกวิชา</p>';
                }

                const compChartDiv = document.getElementById('admin-company-chart');
                if (metrics.company_stats && metrics.company_stats.length > 0) {
                    const maxCount = Math.max(...metrics.company_stats.map(c => c.count), 1);
                    compChartDiv.innerHTML = metrics.company_stats.map(c => {
                        const pct = Math.round((c.count / maxCount) * 100);
                        return `
                            <div class="progress-item">
                                <div class="progress-label">
                                    <span>${c.company_name}</span>
                                    <span class="progress-val">${c.count} ครั้ง</span>
                                </div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar bg-orange" style="width: ${pct}%"></div>
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                    compChartDiv.innerHTML = '<p class="text-muted text-center">ยังไม่มีข้อมูลรายงานสถานประกอบการ</p>';
                }

                const tbody = document.querySelector('#admin-reports-table tbody');
                if (metrics.recent_reports && metrics.recent_reports.length > 0) {
                    tbody.innerHTML = metrics.recent_reports.map(r => {
                        const studentNames = r.students ? r.students.map(s => `${s.student_name} (${s.level} ${s.major})`).join(', ') : 'ไม่มีข้อมูล';
                        return `
                            <tr>
                                <td><strong>${formatThaiDate(r.supervision_date)}</strong></td>
                                <td>${r.teacher_name} ${r.teacher_lastname}<br><span style="font-size:11px;color:var(--text-muted);">${r.department}</span></td>
                                <td>${r.company_name}</td>
                                <td>${studentNames}</td>
                                <td><span class="score-indicator"><i class="fa-solid fa-star"></i> ${r.score_avg}</span></td>
                                <td><span class="status-badge ${getEvalClass(r.eval_result)}">${r.eval_result}</span></td>
                                <td>
                                    <button class="btn btn-outline" style="padding: 4px 8px; font-size: 11px;" onclick="openDetailModal('${r.id}')">
                                        <i class="fa-solid fa-eye"></i> ดูผลงาน
                                    </button>
                                </td>
                            </tr>
                        `;
                    }).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="center text-muted">ยังไม่พบรายงานนิเทศงานจากครูเข้าระบบ</td></tr>';
                }

                // Bind print summary button with latest dashboard metrics
                const printSummaryBtn = document.getElementById('btn-print-summary-all');
                if (printSummaryBtn) {
                    printSummaryBtn.onclick = () => {
                        compileAdminTeacherSummaryPrintDocument(metrics);
                        window.print();
                    };
                }

                // Bind download PDF summary button
                const dlSummaryBtn = document.getElementById('btn-download-summary-pdf');
                if (dlSummaryBtn) {
                    dlSummaryBtn.onclick = () => {
                        showToast('กำลังจัดเตรียมไฟล์ PDF... กรุณารอสักครู่', 'info');
                        compileAdminTeacherSummaryPrintDocument(metrics);
                        downloadElementAsPDF('print-layout-container', `รายงานสรุปการนิเทศ_${new Date().toISOString().slice(0, 10)}.pdf`);
                    };
                }
            }
        })
        .catch(err => console.error(err));
}

function loadAdminTeachersList() {
    const tbody = document.querySelector('#admin-teachers-table tbody');
    if (!tbody) return;

    fetch('admin_api.php?action=list_teachers')
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                const list = res.data;

                if (list.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="5" class="center text-muted">ไม่พบบัญชีครูนิเทศก์ในระบบ</td></tr>`;
                    return;
                }

                tbody.innerHTML = list.map(t => {
                    const deleteBtn = t.id === currentUser.teacher_id 
                        ? `<span style="font-size:11px;color:var(--text-muted);font-style:italic;">บัญชีของคุณ</span>` 
                        : `<button class="btn btn-danger" style="padding: 4px 8px; font-size:11px;" onclick="deleteTeacherAccount(${t.id}, '${t.name}')"><i class="fa-solid fa-trash-can"></i> ลบ</button>`;

                    return `
                        <tr>
                            <td><code>${t.national_id}</code></td>
                            <td><strong>${t.name} ${t.lastname}</strong></td>
                            <td>${t.department}</td>
                            <td><span class="role-badge ${t.role}">${t.role === 'admin' ? 'แอดมินหลังบ้าน' : 'ครูนิเทศก์'}</span></td>
                            <td>${deleteBtn}</td>
                        </tr>
                    `;
                }).join('');
            }
        });
}

function loadAdminCriteriaList() {
    const tbody = document.querySelector('#admin-criteria-table tbody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="3" class="center">กำลังโหลดข้อมูลเกณฑ์ประเมิน...</td></tr>';

    fetch('api_supervision.php?action=list_criteria')
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                const list = res.data;
                if (list.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="center text-muted">ไม่มีหัวข้อประเมินในระบบ</td></tr>';
                    return;
                }
                
                tbody.innerHTML = list.map((c, idx) => {
                    return `
                        <tr>
                            <td class="center"><code>${idx + 1}</code></td>
                            <td><strong>${c.title}</strong></td>
                            <td>
                                <div style="display:flex; gap:8px;">
                                    <button class="btn btn-outline" style="padding:4px 8px; font-size:11px;" onclick="editCriteria(${c.id}, '${c.title}')"><i class="fa-solid fa-edit"></i> แก้ไข</button>
                                    <button class="btn btn-danger" style="padding:4px 8px; font-size:11px;" onclick="deleteCriteria(${c.id}, '${c.title}')"><i class="fa-solid fa-trash"></i> ลบ</button>
                                </div>
                            </td>
                        </tr>
                    `;
                }).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="3" class="center text-danger">เกิดข้อผิดพลาดในการดึงข้อมูลจากฐานข้อมูล</td></tr>';
            }
        });
}

window.editCriteria = function(id, title) {
    const newTitle = prompt("กรุณาแก้ไขข้อความหัวข้อประเมิน:", title);
    if (!newTitle || newTitle.trim() === '' || newTitle.trim() === title) return;

    const formData = new FormData();
    formData.append('id', id);
    formData.append('title', newTitle.trim());

    fetch('admin_api.php?action=edit_criteria', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            showToast(res.message, 'success');
            loadAdminCriteriaList();
        } else {
            showToast(res.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('เกิดข้อผิดพลาดในการแก้ไขเกณฑ์', 'error');
    });
};

window.deleteCriteria = function(id, title) {
    if (confirm(`คุณแน่ใจว่าต้องการลบหัวข้อประเมิน "${title}" ใช่หรือไม่?\nคะแนนที่เคยประเมินในหัวข้อนี้ทั้งหมดจะถูกลบออกถาวร`)) {
        const formData = new FormData();
        formData.append('id', id);

        fetch('admin_api.php?action=delete_criteria', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                showToast(res.message, 'success');
                loadAdminCriteriaList();
            } else {
                showToast(res.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('เกิดข้อผิดพลาดในการลบเกณฑ์', 'error');
        });
    }
};

window.deleteTeacherAccount = function(id, name) {
    if (confirm(`คุณต้องการลบบัญชีผู้ใช้ของ "${name}" ใช่หรือไม่?\nข้อมูลการนิเทศที่เกี่ยวข้องทั้งหมดของครูท่านนี้จะถูกลบออกด้วย`)) {
        const formData = new FormData();
        formData.append('id', id);

        fetch('admin_api.php?action=delete_teacher', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                showToast(res.message, 'success');
                loadAdminTeachersList();
            } else {
                showToast(res.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', 'error');
        });
    }
};

// ==========================================
// Detail Modal View & PDF Compilations
// ==========================================
window.openDetailModal = function(reportId) {
    const modal = document.getElementById('detail-modal');
    const body = document.getElementById('detail-modal-body');
    if (!modal || !body) return;

    fetch(`api_supervision.php?action=get&id=${reportId}`)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                const report = res.data;
                renderReportDetailHtml(report, body);
                compileOfficialThaiGovernmentPrintDocument(report);
                modal.classList.add('active');
            } else {
                showToast(res.message || 'ไม่พบข้อมูลรายงานชิ้นนี้', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('เกิดข้อผิดพลาดในการเข้าถึงเซิร์ฟเวอร์', 'error');
        });
};

function renderReportDetailHtml(r, container) {
    const studentsListHtml = r.students.map(s => {
        return `<li><strong>${s.student_name}</strong> - ชั้นปี ${s.level} ปีที่ ${s.year} (แผนกวิชา${s.major})</li>`;
    }).join('');

    const scoresListHtml = r.scores.map((scoreObj, idx) => {
        return `
            <div class="score-row">
                <span>${idx + 1}. ${scoreObj.title}</span>
                <span style="text-align:center;"><span class="score-badge-detail">${scoreObj.score} / 4</span></span>
            </div>
        `;
    }).join('');

    let photosHtml = '';
    const slots = ['photo_1', 'photo_2', 'photo_3', 'photo_4'];
    let photosCount = 0;
    
    slots.forEach(slot => {
        if (r[slot]) {
            photosCount++;
            photosHtml += `
                <div class="photo-summary-img">
                    <img src="${r[slot]}" alt="ภาพการนิเทศที่ ${photosCount}">
                </div>
            `;
        }
    });

    if (photosCount === 0) {
        photosHtml = `<p class="text-muted" style="grid-column: 1/-1; text-align:center; font-style:italic;">ไม่ได้แนบภาพถ่ายประกอบการนิเทศ</p>`;
    }

    let sigImg = `<span class="text-muted" style="font-style:italic;">ไม่ได้ลงลายมือชื่อ</span>`;
    if (r.signature) {
        sigImg = `<img src="${r.signature}" alt="ลายมือชื่ออาจารย์">`;
    }

    container.innerHTML = `
        <div class="detail-view-container">
            <div class="detail-header">
                <i class="fa-solid fa-graduation-cap detail-logo"></i>
                <h3>แบบสรุปผลการนิเทศการฝึกงานวิชาชีพ</h3>
                <p>วิทยาลัยการอาชีพพนมไพร อ.พนมไพร จ.ร้อยเอ็ด</p>
            </div>
            
            <div class="detail-grid-info">
                <div class="info-field">
                    <label>ปีการศึกษา / ภาคเรียน:</label>
                    <span>ภาคเรียนที่ ${r.semester} ปีการศึกษา ${r.academic_year}</span>
                </div>
                <div class="info-field">
                    <label>วันที่ทำการนิเทศ:</label>
                    <span>${formatThaiDate(r.supervision_date)}</span>
                </div>
                <div class="info-field">
                    <label>อาจารย์ผู้นิเทศ:</label>
                    <span>${r.teacher_name} ${r.teacher_lastname} (${r.department})</span>
                </div>
                <div class="info-field">
                    <label>สถานประกอบการ:</label>
                    <span><strong>${r.company_name}</strong></span>
                </div>
                <div class="info-field" style="grid-column: 1 / -1;">
                    <label>ที่อยู่สถานประกอบการ:</label>
                    <span>${r.company_address}</span>
                </div>
            </div>

            <h4><i class="fa-solid fa-user-graduate"></i> ข้อมูลผู้เรียนที่ได้รับการประเมิน</h4>
            <ul style="padding-left:20px; list-style-type:circle; font-size:13px; margin-bottom:16px;">
                ${studentsListHtml}
            </ul>

            <h4><i class="fa-solid fa-list-check"></i> ผลการประเมินรายหัวข้อ (สเกลคะแนน 1 - 4)</h4>
            <div class="score-details-list">
                <div class="score-row-header">
                    <span>รายการประเมินผล</span>
                    <span style="text-align: center;">คะแนน</span>
                </div>
                ${scoresListHtml}
                <div class="score-row" style="background-color: #f1f5f9; font-weight:700;">
                    <span>คะแนนเฉลี่ยสะสมรวมทั้งหมด (เต็ม 4.00)</span>
                    <span style="text-align: center; color:var(--primary); font-size:15px;">${r.score_avg}</span>
                </div>
                <div class="score-row" style="background-color: #f1f5f9; font-weight:700;">
                    <span>สรุปผลระดับการประเมินการนิเทศ</span>
                    <span style="text-align: center; color:var(--primary); font-size:15px;">
                        <span class="status-badge ${getEvalClass(r.eval_result)}">${r.eval_result}</span>
                    </span>
                </div>
            </div>

            <div class="text-opinion-box">
                <label>ปัญหา / อุปสรรค / ข้อจำกัด:</label>
                <p>${r.problems || 'ไม่มีระบุ'}</p>
            </div>

            <div class="text-opinion-box" style="border-left-color: var(--warning);">
                <label>แนวทางการแก้ไข:</label>
                <p>${r.corrections || 'ไม่มีระบุ'}</p>
            </div>

            <div class="text-opinion-box" style="border-left-color: var(--secondary);">
                <label>ข้อเสนอแนะเพิ่มเติม:</label>
                <p>${r.suggestions || 'ไม่มีข้อเสนอแนะเพิ่มเติม'}</p>
            </div>

            <h4 class="mt-16"><i class="fa-solid fa-images"></i> ภาพกิจกรรมประกอบการนิเทศ</h4>
            <div class="photos-summary-row">
                ${photosHtml}
            </div>

            <div class="detail-signatures">
                <div class="sig-box">
                    <div class="sig-image-container">
                        ${sigImg}
                    </div>
                    <span>( ${r.teacher_name} ${r.teacher_lastname} )<br/>อาจารย์ผู้ลงนิเทศ</span>
                </div>
            </div>
        </div>
    `;

    const printBtn = document.getElementById('btn-print-report');
    if (printBtn) {
        printBtn.onclick = () => {
            window.print();
        };
    }

    const dlPdfBtn = document.getElementById('btn-download-pdf');
    if (dlPdfBtn) {
        dlPdfBtn.onclick = () => {
            showToast('กำลังจัดเตรียมไฟล์ PDF... กรุณารอสักครู่', 'info');
            downloadElementAsPDF('print-layout-container', `รายงานการนิเทศ_${r.company_name}_${r.supervision_date}.pdf`);
        };
    }
}

// ==========================================
// 6. Compile Official Thai Government Document print-out template (ตราครุฑ)
// ==========================================
function compileOfficialThaiGovernmentPrintDocument(r) {
    const printContainer = document.getElementById('print-layout-container');
    if (!printContainer) return;

    const studentRows = r.students.map((s, idx) => {
        return `
            <tr>
                <td style="text-align:center;">${idx+1}</td>
                <td>${s.student_name}</td>
                <td style="text-align:center;">${s.level} ปีที่ ${s.year}</td>
                <td>${s.major}</td>
            </tr>
        `;
    }).join('');



    let photosHtml = '';
    const slots = ['photo_1', 'photo_2', 'photo_3', 'photo_4'];
    let photosCount = 0;
    
    slots.forEach(slot => {
        if (r[slot]) {
            photosCount++;
            photosHtml += `
                <div class="print-photo-item">
                    <img src="${r[slot]}" alt="ภาพการนิเทศที่ ${photosCount}">
                    <span>รูปภาพที่ ${photosCount} ภาพกิจกรรมการเข้าตรวจเยี่ยม</span>
                </div>
            `;
        }
    });

    let photosSection = '';
    if (photosCount > 0) {
        photosSection = `
            <div class="print-photos-section">
                <div class="print-section-title">รูปภาพประกอบกิจกรรมการนิเทศงาน</div>
                <div class="print-photos-grid">
                    ${photosHtml}
                </div>
            </div>
        `;
    }

    let sigImgTag = '';
    if (r.signature) {
        sigImgTag = `<img src="${r.signature}" alt="ลายมือชื่อครูผู้นิเทศ">`;
    }

    printContainer.innerHTML = `
        <div class="print-doc">
            <div class="print-header-logo">
                <img src="logo.png" alt="ตราวิทยาลัย">
            </div>
            
            <div class="print-title">
                รายงานการนิเทศนักศึกษาฝึกงานและฝึกอาชีพในสถานประกอบการ<br>
                วิทยาลัยการอาชีพพนมไพร ภาคเรียนที่ ${r.semester} ปีการศึกษา ${r.academic_year}
            </div>

            <div class="print-field">
                <strong>วันที่ตรวจนิเทศ:</strong> ${formatThaiDate(r.supervision_date)}
                <span style="margin-left: 50px;"><strong>ครูผู้นิเทศ:</strong> ${r.teacher_name} ${r.teacher_lastname}</span>
            </div>
            <div class="print-field">
                <strong>แผนกวิชา:</strong> ${r.department}
            </div>

            <div class="print-section-title">ข้อมูลสถานประกอบการ</div>
            <div class="print-field"><strong>ชื่อสถานประกอบการ:</strong> ${r.company_name}</div>
            <div class="print-field"><strong>ที่ตั้ง/ที่อยู่ของสถานประกอบการ:</strong> ${r.company_address}</div>

            <div class="print-section-title">รายชื่อนักศึกษาผู้รับการตรวจนิเทศ</div>
            <table class="print-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">ลำดับ</th>
                        <th style="width: 42%;">ชื่อ - นามสกุล</th>
                        <th style="width: 20%;">ระดับชั้น</th>
                        <th style="width: 30%;">แผนกวิชา</th>
                    </tr>
                </thead>
                <tbody>
                    ${studentRows}
                </tbody>
            </table>

            <div class="print-section-title">ผลการประเมินทักษะและพฤติกรรมการฝึกงาน</div>
            <div class="print-field" style="margin-top: 8px;"><strong>คะแนนรวมเฉลี่ยประเมินผลการเรียนรู้สะสม (เต็ม 4.00):</strong> ${r.score_avg}</div>
            <div class="print-field"><strong>สรุปมาตรฐานผลการนิเทศ:</strong> ${r.eval_result}</div>

            <div class="print-section-title">ปัญหา / อุปสรรค และสภาพปัญหาที่พิจารณาพบ</div>
            <div class="print-textbox">${r.problems || 'ไม่มีข้อมูลบันทึก'}</div>

            <div class="print-section-title">แนวทางการแก้ไข ปรับปรุงข้อแนะนำ</div>
            <div class="print-textbox">${r.corrections || 'ไม่มีข้อมูลบันทึก'}</div>

            <div class="print-section-title">ข้อคิดเห็นและข้อเสนอแนะเพิ่มเติมจากครูผู้นิเทศ</div>
            <div class="print-textbox">${r.suggestions || 'ไม่มีข้อคิดเห็นเพิ่มเติม'}</div>

            <div class="print-signatures-area">
                <div class="print-sig-box">
                    <div class="print-sig-img">
                        ${sigImgTag}
                    </div>
                    <div>( ${r.teacher_name} ${r.teacher_lastname} )</div>
                    <div style="font-size:14px; margin-top:2px;">ครูผู้นิเทศ</div>
                </div>
            </div>

            <!-- Photos Section (Pushed to second page on print) -->
            ${photosSection}
        </div>
    `;
}

// ==========================================
// 7. Compile Admin Teacher Supervision Summary Print Layout
// ==========================================
function compileAdminTeacherSummaryPrintDocument(metrics) {
    const printContainer = document.getElementById('print-layout-container');
    if (!printContainer || !metrics.teacher_statuses) return;

    let supervisedCount = 0;
    let unsupervisedCount = 0;

    const rows = metrics.teacher_statuses.map((t, idx) => {
        const hasSupervised = parseInt(t.supervision_count) > 0;
        if (hasSupervised) {
            supervisedCount++;
        } else {
            unsupervisedCount++;
        }

        const statusText = hasSupervised 
            ? `<span style="color: #1e7e34; font-weight: bold;">นิเทศแล้ว (${t.supervision_count} ครั้ง)</span>` 
            : `<span style="color: #bd2130; font-weight: bold;">ยังไม่นิเทศ</span>`;

        return `
            <tr>
                <td style="text-align:center;">${idx+1}</td>
                <td><strong>${t.name} ${t.lastname}</strong></td>
                <td>${t.department}</td>
                <td style="text-align:center;">${statusText}</td>
            </tr>
        `;
    }).join('');

    const totalTeachers = metrics.teacher_statuses.length;

    printContainer.innerHTML = `
        <div class="print-doc">
            <div class="print-header-logo">
                <img src="logo.png" alt="ตราวิทยาลัย">
            </div>
            
            <div class="print-title">
                รายงานสรุปสถานะการตรวจนิเทศการฝึกอาชีพของครูนิเทศก์<br>
                วิทยาลัยการอาชีพพนมไพร
            </div>

            <div style="margin-top: 20px; margin-bottom: 20px; font-size: 15px; border: 1px solid #000000; padding: 12px; background-color: #fafafa; display: flex; justify-content: space-around;">
                <span><strong>จำนวนครูนิเทศก์ทั้งหมด:</strong> ${totalTeachers} คน</span>
                <span><strong>นิเทศแล้ว:</strong> <strong style="color: #1e7e34;">${supervisedCount}</strong> คน</span>
                <span><strong>ยังไม่ได้นิเทศ:</strong> <strong style="color: #bd2130;">${unsupervisedCount}</strong> คน</span>
            </div>

            <div class="print-section-title">รายชื่อครูนิเทศก์และสถานะการตรวจนิเทศ</div>
            <table class="print-table">
                <thead>
                    <tr>
                        <th style="width: 10%; text-align: center;">ลำดับ</th>
                        <th style="width: 40%; text-align: left;">ชื่อ - นามสกุลครูนิเทศก์</th>
                        <th style="width: 30%; text-align: left;">แผนกวิชา</th>
                        <th style="width: 20%; text-align: center;">สถานะการนิเทศ</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows}
                </tbody>
            </table>

            <div class="print-signatures-area" style="margin-top: 50px;">
                <div class="print-sig-box">
                    <br><br>
                    <div>( ............................................................ )</div>
                    <div style="font-size:14px; margin-top:4px; font-weight:bold;">ผู้รายงาน/ผู้ตรวจสอบสถานะ</div>
                </div>
            </div>
        </div>
    `;
}

// ==========================================
// Toast Notification Component Helper
// ==========================================
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    let icon = 'fa-circle-check';
    if (type === 'error') icon = 'fa-circle-xmark';
    if (type === 'warning') icon = 'fa-triangle-exclamation';
    if (type === 'info') icon = 'fa-circle-info';

    toast.innerHTML = `
        <i class="fa-solid ${icon}"></i>
        <span>${message}</span>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(15px)';
        toast.style.transition = '0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// ==========================================
// Utilities
// ==========================================
function formatThaiDate(dateString) {
    if (!dateString) return '';
    const months = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    const date = new Date(dateString);
    const day = date.getDate();
    const month = months[date.getMonth()];
    const year = date.getFullYear() + 543;
    return `${day} ${month} ${year}`;
}

function getEvalClass(result) {
    if (result === 'ดีมาก') return 'success';
    if (result === 'ดี') return 'success';
    if (result === 'พอใช้') return 'pending';
    return 'failed';
}

function setupMobileSidebar() {
    const toggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }
}

const dModal = document.getElementById('detail-modal');
if (dModal) {
    const closeBtn = document.getElementById('btn-close-detail-modal');
    const footerBtn = document.getElementById('btn-close-detail-footer');
    
    const closeFn = () => dModal.classList.remove('active');
    if (closeBtn) closeBtn.onclick = closeFn;
    if (footerBtn) footerBtn.onclick = closeFn;
    
    window.addEventListener('click', (e) => {
        if (e.target === dModal) closeFn();
    });
}

// ==========================================
// PDF Export Utility (html2pdf wrapper)
// ==========================================
function downloadElementAsPDF(elementId, filename) {
    const element = document.getElementById(elementId);
    if (!element) {
        showToast('ไม่พบข้อมูลสำหรับสร้าง PDF', 'error');
        return;
    }

    // Save current display style
    const originalDisplay = element.style.display;

    // Temporarily make it visible in the document flow
    element.style.display = 'block';

    // Force a synchronous reflow/layout calculation so html2canvas doesn't read 0 dimensions
    const reflow = element.offsetHeight; 

    // Wait for all images inside the element to be fully loaded
    const images = element.getElementsByTagName('img');
    const promises = Array.from(images).map(img => {
        if (img.complete) return Promise.resolve();
        return new Promise(resolve => {
            img.onload = resolve;
            img.onerror = resolve;
        });
    });

    Promise.all(promises).then(() => {
        // Wait another 150ms to allow layout engine to fully settle and apply web fonts/images
        setTimeout(() => {
            const opt = {
                margin:       [15, 20, 15, 20], // top, left, bottom, right in mm
                filename:     filename,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { 
                    scale: 2, 
                    useCORS: true, 
                    logging: false,
                    scrollX: 0,
                    scrollY: 0
                },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak:    { mode: ['css', 'legacy'] }
            };

            html2pdf().set(opt).from(element).save().then(() => {
                element.style.display = originalDisplay;
                showToast('ดาวน์โหลดไฟล์ PDF เรียบร้อยแล้ว', 'success');
            }).catch(err => {
                console.error('PDF export error:', err);
                element.style.display = originalDisplay;
                showToast('เกิดข้อผิดพลาดในการดาวน์โหลด PDF', 'error');
            });
        }, 150);
    });
}
