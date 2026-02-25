let csrfToken = document.querySelector('input[name="_token"]')?.value;

$(document).ready(function() {
    initializeComponents();
    bindEvents();
    checkFormValidation();
});

function initializeComponents() {
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
  popoverTriggerList.map(function (popoverTriggerEl) {
      return new bootstrap.Popover(popoverTriggerEl);
  });

  setTimeout(function() {
      $('.alert').fadeOut('slow');
  }, 5000);

  if (typeof $.fn.select2 !== 'undefined') {
      $('.select2').select2();
  }

  $('.format-number').each(function() {
      let number = parseInt($(this).text());
      if (!isNaN(number)) {
          $(this).text(number.toLocaleString());
      }
  });
}

function bindEvents() {
  $(document).on('click', '.btn-delete', function(e) {
      e.preventDefault();
      let href = $(this).attr('href');
      let itemName = $(this).data('item') || 'this item';

      if (confirm('Are you sure you want to delete ' + itemName + '? This action cannot be undone.')) {
          window.location.href = href;
      }
  });

  $('form').on('submit', function() {
      let submitBtn = $(this).find('button[type="submit"]');
      let originalText = submitBtn.html();

      submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Processing...').prop('disabled', true);

      setTimeout(function() {
          submitBtn.html(originalText).prop('disabled', false);
      }, 10000);
  });

  $('.numeric-only').on('input', function() {
      this.value = this.value.replace(/[^0-9]/g, '');
  });

  $('.uppercase').on('input', function() {
      this.value = this.value.toUpperCase();
  });

  let searchTimer;
  $('.search-input').on('input', function() {
      let query = $(this).val();
      let target = $(this).data('target');

      clearTimeout(searchTimer);
      searchTimer = setTimeout(function() {
          performSearch(query, target);
      }, 300);
  });

  $('.btn-print').on('click', function(e) {
      e.preventDefault();
      window.print();
  });

  $('.btn-export').on('click', function(e) {
      e.preventDefault();
      let format = $(this).data('format');
      let table = $(this).data('table');
      exportData(format, table);
  });

  $('#quantity').on('input', function() {
      let quantity = parseInt($(this).val());
      let type = $('#transaction_type').val();
      let availableStock = parseInt($('#available_stock').val());

      if (type === 'stock-out' && quantity > availableStock) {
          $(this).addClass('is-invalid');
          $('.invalid-feedback').text('Quantity cannot exceed available stock (' + availableStock + ')');
      } else {
          $(this).removeClass('is-invalid');
      }
  });

  if ($('.auto-refresh').length) {
      setInterval(function() {
          location.reload();
      }, 10000);
  }
}

function checkFormValidation() {
    $('.form-control, .form-select').on('blur', function() {
        validateField($(this));
    });

    $('form').on('submit', function(e) {
        let isValid = true;
        let form = $(this);

        form.find('[required]').each(function() {
            if (!validateField($(this))) {
                isValid = false;
            }
        });

        if (form.hasClass('transaction-form')) {
            if (!validateTransaction(form)) {
                isValid = false;
            }
        }

        if (!isValid) {
            e.preventDefault();
            showNotification('Please correct the errors before submitting.', 'error');
        }
    });
}

function validateField(field) {
    let value = field.val().trim();
    let isValid = true;
    let errorMessage = '';

    if (field.prop('required') && !value) {
        isValid = false;
        errorMessage = 'This field is required.';
    }

    if (field.attr('type') === 'email' && value) {
        let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email address.';
        }
    }

    if (field.attr('name') === 'password' && value) {
        if (value.length < 6) {
            isValid = false;
            errorMessage = 'Password must be at least 6 characters long.';
        }
    }

    if (field.attr('name') === 'confirm_password' && value) {
        let password = $('input[name="password"]').val();
        if (value !== password) {
            isValid = false;
            errorMessage = 'Passwords do not match.';
        }
    }

    if (field.attr('name') === 'username' && value) {
        let usernameRegex = /^[a-zA-Z0-9_]{3,20}$/;
        if (!usernameRegex.test(value)) {
            isValid = false;
            errorMessage = 'Username must be 3-20 characters and contain only letters, numbers, and underscores.';
        }
    }

    if (isValid) {
        field.removeClass('is-invalid').addClass('is-valid');
        field.siblings('.invalid-feedback').hide();
    } else {
        field.removeClass('is-valid').addClass('is-invalid');
        field.siblings('.invalid-feedback').text(errorMessage).show();
    }

    return isValid;
}

function validateTransaction(form) {
    let isValid = true;
    let quantity = parseInt(form.find('#quantity').val());
    let type = form.find('#transaction_type').val();
    let availableStock = parseInt(form.find('#available_stock').val());

    if (type === 'stock-out' && quantity > availableStock) {
        form.find('#quantity').addClass('is-invalid');
        form.find('#quantity').siblings('.invalid-feedback').text('Quantity cannot exceed available stock.').show();
        isValid = false;
    }

    return isValid;
}

function performSearch(query, target) {
    if (target === 'table') {
        if ($.fn.DataTable && $.fn.DataTable.isDataTable('#dataTable')) {
            $('#dataTable').DataTable().search(query).draw();
        } else {
            searchInTable(query);
        }
    }
}

function searchInTable(query) {
    let table = $('.table tbody');
    let rows = table.find('tr');

    rows.each(function() {
        let row = $(this);
        let text = row.text().toLowerCase();

        if (text.includes(query.toLowerCase())) {
            row.show();
        } else {
            row.hide();
        }
    });
}

function exportData(format, tableId) {
    let table = $('#' + tableId);
    let data = [];
    let headers = [];

    table.find('thead th').each(function() {
        headers.push($(this).text().trim());
    });

    table.find('tbody tr:visible').each(function() {
        let row = [];
        $(this).find('td').each(function() {
            row.push($(this).text().trim());
        });
        data.push(row);
    });

    if (format === 'csv') {
        exportToCSV(data, headers);
    } else if (format === 'excel') {
        exportToExcel(data, headers);
    }
}

function exportToCSV(data, headers) {
    let csv = '';
    csv += headers.join(',') + '\n';

    data.forEach(function(row) {
        csv += row.map(function(cell) {
            return '"' + cell.replace(/"/g, '""') + '"';
        }).join(',') + '\n';
    });

    downloadFile(csv, 'export.csv', 'text/csv');
}

function exportToExcel(data, headers) {
    exportToCSV(data, headers);
}

function downloadFile(content, filename, contentType) {
    let blob = new Blob([content], { type: contentType });
    let url = window.URL.createObjectURL(blob);
    let a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function showNotification(message, type = 'info') {
    let alertClass = 'alert-' + (type === 'error' ? 'danger' : type);
    let iconClass = type === 'success' ? 'fa-check-circle' :
                    type === 'error' ? 'fa-exclamation-circle' :
                    type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';

    let notification = `
        <div class="alert ${alertClass} alert-dismissible fade show notification-alert" role="alert">
            <i class="fas ${iconClass}"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    $('.notification-alert').remove();

    $('main').prepend(notification);

    setTimeout(function() {
        $('.notification-alert').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
    }).format(amount);
}

function formatDate(date, format = 'YYYY-MM-DD HH:mm:ss') {
    if (typeof moment !== 'undefined') {
        return moment(date).format(format);
    }
    return new Date(date).toLocaleString();
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

function isInViewport(element) {
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

function scrollToElement(elementId, offset = 100) {
    const element = document.getElementById(elementId);
    if (element) {
        const elementPosition = element.offsetTop - offset;
        window.scrollTo({
            top: elementPosition,
            behavior: 'smooth'
        });
    }
}

function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            showNotification('Text copied to clipboard!', 'success');
        });
    } else {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showNotification('Text copied to clipboard!', 'success');
    }
}

function generateRandomString(length = 10) {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let result = '';
    for (let i = 0; i < length; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
}

function validateFileUpload(file, maxSize = 5 * 1024 * 1024, allowedTypes = ['image/jpeg', 'image/png', 'image/gif']) {
    if (file.size > maxSize) {
        showNotification('File size exceeds the maximum limit of ' + (maxSize / 1024 / 1024) + 'MB', 'error');
        return false;
    }

    if (!allowedTypes.includes(file.type)) {
        showNotification('File type not allowed. Please upload: ' + allowedTypes.join(', '), 'error');
        return false;
    }

    return true;
}

function handleAjaxError(xhr, status, error) {
    console.error('AJAX Error:', {xhr, status, error});

    let message = 'An error occurred. Please try again.';

    if (xhr.status === 401) {
        message = 'Session expired. Please login again.';
        setTimeout(() => {
            window.location.href = '/index.php';
        }, 2000);
    } else if (xhr.status === 403) {
        message = 'Access denied. You do not have permission to perform this action.';
    } else if (xhr.status === 404) {
        message = 'Resource not found.';
    } else if (xhr.status >= 500) {
        message = 'Server error. Please contact support if the problem persists.';
    }

    showNotification(message, 'error');
}

$(document).ajaxError(handleAjaxError);

if (csrfToken) {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': csrfToken
        }
    });
}
