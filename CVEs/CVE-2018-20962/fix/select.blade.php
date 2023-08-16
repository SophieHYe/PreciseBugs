{{-- single relationships (1-1, 1-n) --}}
<span>
    <?php
        $attributes = $crud->getModelAttributeFromRelation($entry, $column['entity'], $column['attribute']);
        if (count($attributes)) {
            echo e(implode(', ', $attributes));
        } else {
            echo '-';
        }
    ?>
</span>
