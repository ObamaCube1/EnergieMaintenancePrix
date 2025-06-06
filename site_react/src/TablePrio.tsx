import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom'; // Pour récupérer le nom de la centrale
import './TablePrio.css';

function TablePrio() {
    interface HeureData {
        heure: number;
        prio: string[];
        meilleur?: boolean; // <- ajouté
        [turbineId: string]: number | number[] | string[] | boolean | undefined;
    }


    const { nom } = useParams<{ nom: string }>(); // <- nom de la centrale depuis l'URL
    const [data, setData] = useState<HeureData[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (!nom) return;

        fetch(`http://127.0.0.1/manipRTE.php?action=TablePrio&nom=${encodeURIComponent(nom)}`)
            .then(res => res.json())
            .then((json: HeureData[]) => {
                setData(json);
                setLoading(false);
            })
            .catch((err) => {
                console.error("Erreur lors du chargement :", err);
                setLoading(false);
            });
    }, [nom]);

    if (loading) return <p>Chargement…</p>;
    if (data.length === 0) return <p>Aucune donnée disponible</p>;

    const turbineIds = Object.keys(data[0]).filter(key => key !== 'heure' && key !== 'prio');

    return (
        <div className="TablePrio">
            <table className="TablePrioTable">
                <thead>
                <tr>
                    <th>Heure</th>
                    {turbineIds.map(id => (
                        <th key={id}>{id}</th>
                    ))}
                    <th>Priorité</th>
                </tr>
                </thead>
                <tbody>
                {data.map((heureData, index) => (
                    <tr key={index}>
                        <td>{heureData.heure}</td>
                        {turbineIds.map(id => (
                            <td key={id}>{heureData[id]}</td>
                        ))}
                        <td>{heureData.prio.join(', ')}</td>
                    </tr>
                ))}
                </tbody>
            </table>
        </div>
    );
}

export default TablePrio;
