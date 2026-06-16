<script>
    $(() => {
        const parentForm = $('select[name="parent_id"]').closest('.form-group, .mb-3, .col-md-12');
        const workerForm = $('select[name="worker_id"]').closest('.form-group, .mb-3, .col-md-12');
        const defective = !!@json($line?->defective);
        const old = @json(old('defective'));

        let toggle = defective || (old != null);
        if (toggle) {
            parentForm.removeClass('d-none');
            workerForm.addClass('d-none');
        } else {
            workerForm.removeClass('d-none');
            parentForm.addClass('d-none');
        }

        $('input[name="defective"]').on('switchChange.bootstrapSwitch', (e) => {
            if (toggle) {
                parentForm.addClass('d-none');
                workerForm.removeClass('d-none');
            } else {
                parentForm.removeClass('d-none');
                workerForm.addClass('d-none');
            }
            toggle = !toggle;
        });
    });
</script>
